<?php

namespace Maidmaid\WebotBundle\Command;

use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DomCrawler\Crawler;

class LocalCommand extends \Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand
{
	/**
	 * @var \GuzzleHttp\Client
	 */
	protected $client;
	
	/**
	 * @var \Symfony\Component\Console\Helper\FormatterHelper
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
	 * @var \Symfony\Bridge\Monolog\Logger
	 */
	protected $logger;

	public function __construct($name = null)
	{
		parent::__construct($name);
		$this->client = new \GuzzleHttp\Client();
		$this->formatter = new \Symfony\Component\Console\Helper\FormatterHelper();
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
		
        // Recherches
        $searchs = array(
			array('what' => 'Café, Restaurant', 'where' => 'Valais-Central (Région)'),
            array('what' => 'Café, Restaurant', 'where' => 'Bas-Valais (Région)')
        );
        
        foreach($searchs as $s => $search)
        {
            $lastPage = 1;
            for($page = 1; $page <= $lastPage; $page++)
            {
                // Message
				$this->output->writeln($this->formatter->formatBlock(utf8_decode(sprintf('%s, %s, %s/%s', $search['what'], $search['where'], $page, $lastPage)), 'question', true));

                // Requête de base
                $request = $this->client->createRequest('GET', 'http://tel.local.ch');
                $request->setPath('fr/q');

                // Query string
                $query = $request->getQuery();
                $query->add('what', $search['what']);
                $query->add('where', $search['where']);
                $query->add('page', $page);

                // Réponse
                $response = $this->client->send($request);
                $html = (string) $response->getBody();
                $crawler = new Crawler($html);

                // Dernière page
                $lastPage = (int) $crawler->filter('.pagination .page a')->last()->text();
                
                // Infos des enregistrements
                $listings = $crawler->filter('.local-listing')->each(function(Crawler $node, $i) use ($output) 
                {
                    $listing['name'] = utf8_decode(trim($node->filter('h2 a')->text()));
                    $listing['number'] = utf8_decode(trim($node->filter('.number')->text()));
					
					try
					{
						$url = new \GuzzleHttp\Url('http', trim($node->filter('.url')->text()));
						$listing['url'] = $url;
					}
					catch(\Exception $e)
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
					catch(\Exception $e)
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
	
	protected function analyzeStylesheet(Crawler $stylesheet, $listing)
	{
		// URL
		$href = $stylesheet->text();
		$temp = \GuzzleHttp\Url::fromString($href);
		
		$host = $temp->getHost();
		if(empty($host))
		{
			$url = clone $listing['url'];
			$url->setPath($temp->getPath());
			$url->setQuery($temp->getQuery());
		}
		else
		{
			$url = \GuzzleHttp\Url::fromString($href); 
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
		catch(\Exception $e)
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
