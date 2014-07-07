<?php

namespace Maidmaid\WebotBundle\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DomCrawler\Crawler;

class LocalCommand extends \Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('webot:local')
            ->setDescription('Hello World example command');
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        /* @var $logger \Psr\Log\LoggerInterface */
        $logger = $this->getContainer()->get('logger');
        $logger->info('salut');
        $logger->warning('salut');
        $logger->notice('Coucou');
        
        // Client HTTP
        $client = new \GuzzleHttp\Client();

        // Recherches
        $searchs = array(
            array('what' => 'Café, Restaurant', 'where' => 'Bas-Valais (Région)'),
            array('what' => 'Café, Restaurant', 'where' => 'Valais-Central (Région)')
        );
        
        foreach($searchs as $s => $search)
        {
            $lastPage = 1;
            for($page = 1; $page <= $lastPage; $page++)
            {
                // Attente de la nouvelle requête
                $output->writeln('Attente pour la prochaine requête');
                //$time = rand(30, 60) * 1000;
				$time = rand(5, 10);
                $progress = new ProgressBar($output, $time);
                $progress->setEmptyBarCharacter(' ');
                $progress->start();
                for($i = 0; $i < $time; $i++)
                {
                    $progress->advance();
                    sleep(1);
                }

                // Message
                $output->writeln(printf('Requête %s %s, page %s/%s', $search['what'], $search['where'], $page, $lastPage));

                // Requête de base
                $request = $client->createRequest('GET', 'http://tel.local.ch');
                $request->setPath('fr/q');

                // Query string
                $query = $request->getQuery();
                $query->add('what', $search['what']);
                $query->add('where', $search['where']);
                $query->add('page', $page);

                // Réponse
                $response = $client->send($request);
                $html = $response->getBody()->__toString();
                $crawler = new Crawler($html);

                // Dernière page
                $lastPage = (int) $crawler->filter('.pagination .page a')->last()->text();
                
                // Infos des inregistrements
                $listings = $crawler->filter('.local-listing')->each(function(Crawler $node, $i)
                {
                    $listing['name'] = utf8_decode(trim($node->filter('h2 a')->text()));
                    $listing['number'] = utf8_decode(trim($node->filter('.number')->text()));
                    $listing['url'] = utf8_decode(trim($node->filter('.url')->text()));
                    return $listing;
                });
                
                // Affichage table
                $table = new Table($output);
                $table->setHeaders(array('Nom', 'Tel', 'Url'));
                $table->addRows($listings);
                $table->render();
            }
        }
    }
}
