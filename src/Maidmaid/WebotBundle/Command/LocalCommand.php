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
    /** @var Client */
    protected $client;

    /** @var FormatterHelper */
    protected $formatter;

    /** @var OutputInterface */
    protected $output;

    /** @var InputInterface */
    protected $input;

    /** @var Logger */
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
        $searchs = Yaml::parse(file_get_contents(__DIR__.'/../Resources/config/data.yml'));
        $cantons = $searchs['cantons'];
        $what = $searchs['what'];

        foreach ($cantons as $canton => $communes) {
            foreach ($communes as $i => $commune) {
                $lastPage = 1;
                for ($page = 1; $page <= $lastPage; $page++) {
                    // Write title
                    $titles = array(
                        utf8_decode($what),
                        utf8_decode(sprintf('%s (%s/%s)', $canton, array_search($canton, array_keys($cantons)) + 1, count($cantons))),
                        utf8_decode(sprintf('%s (%s/%s)', $commune, $i + 1, count($communes))),
                        sprintf('page %s/%s', $page, $lastPage),
                    );
                    $this->output->writeln($this->formatter->formatBlock($titles, 'question', true));

                    // Create request
                    $request = $this->client->createRequest('GET', 'http://tel.local.ch');
                    $request->setPath('fr/q');
                    $query = $request->getQuery();
                    $query->add('what', $what);
                    $query->add('where', $commune);
                    $query->add('page', $page);

                    // Get response
                    $response = $this->client->send($request);
                    $html = (string) $response->getBody();
                    $crawler = new Crawler($html);

                    // Set the last page
                    try {
                        $lastPage = (int) $crawler->filter('.pagination .page a')->last()->text();
                    } catch (Exception $e) {
                        $lastPage = 1;
                    }

                    // Filter informations
                    $listings = $crawler->filter('.local-listing')->each(function (Crawler $node) {
                        $listing['name'] = utf8_decode(trim($node->filter('h2 a')->text()));
                        $listing['number'] = utf8_decode(trim($node->filter('.number')->text()));

                        try {
                            $url = new Url('http', trim($node->filter('.url')->text()));
                            $listing['url'] = $url;
                        } catch (Exception $e) {
                            $listing['url'] = '';
                        }

                        return $listing;
                    });

                    // Render table of listings
                    $table = new Table($output);
                    $table->setHeaders(array('Nom', 'Tel', 'Url'));
                    $table->addRows($listings);
                    $table->render();

                    foreach ($listings as $listing) {
                        if (empty($listing['url'])) {
                            continue;
                        }

                        // Write subtitle
                        $subtitle = sprintf('%s (%s)', $listing['name'], $listing['url']);
                        $this->output->writeln($this->formatter->formatBlock($subtitle, 'question'));

                        // Get response
                        try {
                            $response = $this->client->get($listing['url']);
                        } catch (Exception $e) {
                            $this->output->writeln(sprintf('<error>%s</error>', $e->getMessage()));
                            $this->logger->warning(sprintf('%s - %s', $listing['url'], $e->getMessage()));
                            continue;
                        }

                        // Filter stylesheets
                        $html = (string) $response->getBody();
                        $crawler = new Crawler($html);
                        $stylesheets = $crawler->filterXPath("//link[@rel='stylesheet']/@href");
                        $styles = $crawler->filterXPath("//style");

                        // Write message
                        $message = sprintf(
                            '<comment>%s</comment> stylesheet(s) + <comment>%s</comment> style tag(s) founded',
                            $stylesheets->count(),
                            $styles->count()
                        );
                        $this->output->writeln($message);

                        // Analyze
                        $countTag = $styles->each(function (Crawler $style) {
                            return $this->analyzeStyleTag($style);
                        });
                        $countSheet = $stylesheets->each(function (Crawler $stylesheet) use ($listing) {
                            return $this->analyzeStylesheet($stylesheet, $listing);
                        });

                        // Write total
                        $count = array_sum(array_merge($countTag, $countSheet));
                        $tag = $count == 0 ? 'comment' : 'info';
                        $message = sprintf('TOTAL : <%s>%s @media founded</%s>', $tag, $count, $tag);
                        $this->output->writeln($message);
                    }
                }
            }
        }
    }

    protected function analyzeStylesheet(Crawler $stylesheet, $listing)
    {
        // Get href attribute
        $href = $stylesheet->text();
        $temp = Url::fromString($href);

        // Set host
        $host = $temp->getHost();
        if (empty($host)) {
            $url = clone $listing['url'];
            $url->setPath($temp->getPath());
            $url->setQuery($temp->getQuery());
        } else {
            $url = Url::fromString($href);
        }

        // Set scheme
        $scheme = $url->getScheme();
        if (empty($scheme)) {
            $url->setScheme('http');
        }

        // Remove dot segments
        $url->removeDotSegments();

        // Write URL
        $this->output->write((string) $url);

        // Get response
        try {
            $response = $this->client->get($url);
        } catch (Exception $e) {
            $this->output->writeln(sprintf(' <error>%s</error>', $e->getMessage()));
            $this->logger->warning(sprintf('%s - %s', $url, $e->getMessage()));

            return 0;
        }

        // Check body's response
        if ($response->getBody() === null) {
            $this->output->writeln(' <error>empty body</error>');

            return 0;
        }

        // Count media tag
        $css = (string) $response->getBody();
        $count = $this->countMediaTag($css);

        return $count;
    }

    protected function countMediaTag($css)
    {
        //$countImport = substr_count($css, '@import');
        $count = substr_count($css, '@media');
        $tag = $count == 0 ? 'comment' : 'info';
        $message = sprintf(' <%s>%s @media founded</%s>', $tag, $count, $tag);
        $this->output->writeln($message);

        return $count;
    }

    protected function analyzeStyleTag(Crawler $style)
    {
        $this->output->write('style tag');
        $css = $style->text();

        return $this->countMediaTag($css);
    }
}
