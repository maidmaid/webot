<?php

namespace Maidmaid\WebotBundle\Command;

use GuzzleHttp\Message\Response;
use NumberPlate\Searcher;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\EventDispatcher\GenericEvent;

class NumberPlateCommand extends ContainerAwareCommand
{
	/* @var $searcher Searcher */
	private $searcher;
	
	public function __construct($name = null)
	{
		parent::__construct($name);
		$this->searcher = new Searcher();
	}
	
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('webot:numberplate')
            ->setDescription('')
			->addOption(
				'min-sleep',
				null,
				InputOption::VALUE_REQUIRED,
				'min sleep (seconds)',
				30
			)
			->addOption(
				'max-sleep',
				null,
				InputOption::VALUE_REQUIRED,
				'max sleep (seconds)',
				60
			);
	}

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
		$this->searcher = new Searcher();
		$subscriber = new \Maidmaid\WebotBundle\Event\SearcherSubscriber($input, $output);
		$this->searcher->getDispatcher()->addSubscriber($subscriber);
		
		// Search
		$numberplate = rand(1, 99999);
		$output->writeln(sprintf('Search for: <question>%s</question>', $numberplate));
		$data = $this->searcher->search($numberplate);
		
		// Save result
		$np = new \Maidmaid\WebotBundle\Entity\Numberplate();
		$np->setNumberplate($numberplate);
		if(empty($data))
		{
			$np->setInfo($this->searcher->getLastError());
		}
		else
		{
			$np->setCategory($data['category']);
			$np->setSubcategory($data['subcategory']);
			$np->setName($data['name']);
			$np->setAddress($data['address']);
			$np->setComplement($data['complement']);
			$np->setLocality($data['locality']);	
		}
		
		/* @var $doctrine \Doctrine\Bundle\DoctrineBundle\Registry */
		$doctrine = $this->getContainer()->get('doctrine');
		$em = $doctrine->getManager();
		$em->persist($np);
		$em->flush();
		
		// Show result
		$table = new Table($output);
		$table->setHeaders(array_keys($np->toArray()));
		$table->addRow((array) $np);
		$table->render();
		
		$this->execute($input, $output);
    }
}
