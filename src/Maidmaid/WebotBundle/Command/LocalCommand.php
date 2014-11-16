<?php

namespace Maidmaid\WebotBundle\Command;

use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Url;
use Symfony\Bridge\Monolog\Logger;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Helper\FormatterHelper;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\Yaml\Yaml;

class LocalCommand extends ContainerAwareCommand
{
	/**
	 * @var Client
	 */
	protected $client;
	
	/**
	 * @var FormatterHelper
	 */
	protected $formatter;
	
	/**
	 * @var OutputInterface
	 */
	protected $output;
	
	/**
	 * @var InputInterface
	 */
	protected $input;
	
	/**
	 * @var Logger
	 */
	protected $logger;

	public function __construct($name = null)
	{
		parent::__construct($name);
		$this->client = new Client();
		$this->formatter = new FormatterHelper();
	}
	
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setName('webot:local');
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {	
		$this->input = $input;
		$this->output = $output;
		$this->logger = $this->getContainer()->get('monolog.logger.webot.local');

        $searchs = Yaml::parse(file_get_contents(__DIR__ . '/../Resources/config/data.yml'));
        $what = 'Café, Restaurant';
        $cantons = $searchs['cantons'];
        
        foreach($cantons as $canton => $communes)
        {
            foreach($communes as $i => $commune)
            {
                $lastPage = 1;
                for($page = 1; $page <= $lastPage; $page++)
                {
                    // Title
                    $titles = array(
                        utf8_decode($what),
                        utf8_decode(sprintf('%s (%s/%s)', $canton,  array_search($canton, array_keys($cantons)) + 1, count($cantons))),
                        utf8_decode(sprintf('%s (%s/%s)', $commune, $i + 1, count($communes))),
                        sprintf('page %s/%s', $page, $lastPage)
                    );
                    $this->output->writeln($this->formatter->formatBlock($titles, 'question', true));

                    // Requête de base
                    $request = $this->client->createRequest('GET', 'http://tel.local.ch');
                    $request->setPath('fr/q');

                    // Query string
                    $query = $request->getQuery();
                    $query->add('what', $what);
                    $query->add('where', $commune);
                    $query->add('page', $page);

                    // Réponse
                    $response = $this->client->send($request);
                    $html = (string) $response->getBody();
                    $crawler = new Crawler($html);

                    // Dernière page
                    try {
                        $lastPage = (int) $crawler->filter('.pagination .page a')->last()->text();
                    } catch (Exception $e) {
                        $lastPage = 1;
                    }

                    // Infos des enregistrements
                    $listings = $crawler->filter('.local-listing')->each(function(Crawler $node, $i) use ($output)
                    {
                        $listing['name'] = utf8_decode(trim($node->filter('h2 a')->text()));
                        $listing['number'] = utf8_decode(trim($node->filter('.number')->text()));

                        try
                        {
                            $url = new Url('http', trim($node->filter('.url')->text()));
                            $listing['url'] = $url;
                        }
                        catch(Exception $e)
                        {
                            $listing['url'] = '';
                        }

                        return $listing;
                    });

                    // Affichage table
                    $table = new Table($output);
                    $table->setHeaders(array('Nom', 'Tel', 'Url'));
                    $table->addRows($listings);
                    $table->render();

                    foreach($listings as $listing)
                    {
                        if(empty($listing['url']))
                        {
                            continue;
                        }

                        $this->output->writeln($this->formatter->formatBlock(sprintf('%s (%s)', $listing['name'], $listing['url']), 'question'));

                        try
                        {
                            $response = $this->client->get($listing['url']);
                        }
                        catch(Exception $e)
                        {
                            $this->output->writeln(sprintf('<error>%s</error>', $e->getMessage()));
                            $this->logger->warning(sprintf('%s - %s', $listing['url'], $e->getMessage()));
                            continue;
                        }

                        $html = (string) $response->getBody();
                        $crawler = new Crawler($html);

                        $stylesheets = $crawler->filterXPath("//link[@rel='stylesheet']/@href");
                        $styles = $crawler->filterXPath("//style");

                        $this->output->writeln(sprintf('<comment>%s</comment> stylesheet(s) + <comment>%s</comment> style tag(s) founded', count($stylesheets), $styles->count()));

                        $countTag = $styles->each(function(Crawler $style){
                            return $this->analyzeStyleTag($style);
                        });

                        $countSheet = $stylesheets->each(function(Crawler $stylesheet) use ($listing) {
                            return $this->analyzeStylesheet($stylesheet, $listing);
                        });

                        // Total
                        $count = array_sum(array_merge($countTag, $countSheet));
                        $tag = $count == 0 ? 'comment' : 'info';
                        $this->output->writeln(sprintf('TOTAL : <%s>%s @media founded</%s>', $tag, $count, $tag));
                    }
                }
            }
        }
    }
	
	protected function analyzeStylesheet(Crawler $stylesheet, $listing)
	{
		// URL
		$href = $stylesheet->text();
		$temp = Url::fromString($href);
		
		$host = $temp->getHost();
		if(empty($host))
		{
			$url = clone $listing['url'];
			$url->setPath($temp->getPath());
			$url->setQuery($temp->getQuery());
		}
		else
		{
			$url = Url::fromString($href);
		}
		
		$scheme = $url->getScheme();
		if(empty($scheme))
		{
			$url->setScheme('http');
		}
		
		$url->removeDotSegments();
		
		$this->output->write((string) $url);
		
		try
		{
			$response = $this->client->get($url);
		}
		catch(Exception $e)
		{
			$this->output->writeln(sprintf(' <error>%s</error>', $e->getMessage()));
			$this->logger->warning(sprintf('%s - %s', $url, $e->getMessage()));
			return 0;
		}

		if(is_null($response->getBody()))
		{
			$this->output->writeln(' <error>empty body</error>');
			return 0;
		}

		$css = (string) $response->getBody();
		$count = $this->countMediaTag($css);
		
		return $count;
	}
	
	protected function countMediaTag($css)
	{
		//$countImport = substr_count($css, '@import');
		$count = substr_count($css, '@media');
		$tag = $count == 0 ? 'comment' : 'info';
		$this->output->writeln(sprintf(' <%s>%s @media founded</%s>', $tag, $count, $tag));
		return $count;
	}
	
	protected function analyzeStyleTag(Crawler $style)
	{
		$this->output->write('style tag');
		$css = $style->text();
		return $this->countMediaTag($css);
	}
}
