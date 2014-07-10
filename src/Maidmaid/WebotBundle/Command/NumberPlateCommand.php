<?php

namespace Maidmaid\WebotBundle\Command;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Maidmaid\WebotBundle\Entity\Numberplate;
use Maidmaid\WebotBundle\Event\SearcherSubscriber;
use NumberPlate\Searcher;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Helper\FormatterHelper;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class NumberPlateCommand extends ContainerAwareCommand
{
	/**
     * {@inheritdoc}
     */
	public function __construct($name = null)
	{
		parent::__construct($name);		
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
		$searcher = new Searcher();
		$subscriber = new SearcherSubscriber($input, $output);
		$searcher->getDispatcher()->addSubscriber($subscriber);
		$formatter = new FormatterHelper();
		
		// Search
		$numberplate = rand(1, 99999);
		$output->writeln($formatter->formatBlock('Search ' . $numberplate, 'question', true));
		$data = $searcher->search($numberplate);
		
		// Save result
		$np = new Numberplate();
		$np->setNumberplate($numberplate);
		if(empty($data))
		{
			$np->setInfo($searcher->getLastError());
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
		
		/* @var $doctrine Registry */
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
