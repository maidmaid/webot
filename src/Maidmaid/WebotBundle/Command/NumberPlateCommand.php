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
		$subscriber = new SearcherSubscriber($input, $output);
		
		$formatter = new FormatterHelper();
		$table = new Table($output);
		$temp = new Numberplate();
		$table->setHeaders(array_keys($temp->toArray()));
		
		/* @var $doctrine Registry */
		$doctrine = $this->getContainer()->get('doctrine');
		$em = $doctrine->getManager();
		/* @var $repository \Maidmaid\WebotBundle\Entity\NumberplateRepository */
		$repository = $doctrine->getRepository('MaidmaidWebotBundle:Numberplate');
		$i = 0;
		
		while(true)
		{
			$i++;
			if($i % 10 == rand(0, 10) || $i == 1)
			{
				$searcher = new Searcher();
				$searcher->getDispatcher()->addSubscriber($subscriber);
			}

			// Search
			$gap = $repository->getRandomGap();
			$numberplate = rand($gap['start'], $gap['end']);
			$output->writeln($formatter->formatBlock('Search #' . $i . ': ' . $numberplate, 'question', true));
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

			$em->persist($np);
			$em->flush();

			// Show result
			$table->setRows(array($np->toArray()));
			$table->render();
		}
	}
}
