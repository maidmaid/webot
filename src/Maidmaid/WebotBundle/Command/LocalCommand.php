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
                $html = $response->getBody()->__toString();
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
						$url = utf8_decode(trim($node->filter('.url')->text()));
						$urlParsed = parse_url($url);
						$listing['url'] = isset($urlParsed["scheme"]) ? $url : 'http://' . $url;
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
						continue;
					}
					
					$html = $response->getBody()->__toString();
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
		$hrefParsed = parse_url($href);
		if(!isset($hrefParsed['host']))
		{
			$href = substr($href, 0, 1) == '/' ? $href : '/' . $href;
			$href = $listing['url'] . $href;
		}
		
		$this->output->write($href);
		
		try
		{
			$response = $this->client->get($href);
		}
		catch(\Exception $e)
		{
			$this->output->writeln(sprintf(' <error>%s</error>', $e->getMessage()));
			return 0;
		}

		if(is_null($response->getBody()))
		{
			$this->output->writeln(' <error>empty body</error>');
			return 0;
		}

		$css = $response->getBody()->__toString();
		$count = $this->countMediaTag($css);
		
		return $count;
	}
	
	protected function countMediaTag($css)
	{
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
