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
		
		// Event cookie.initialize		
		$initializeCookie = function(GenericEvent $e) use(&$output, &$input)
		{
			$cookies = $e->getSubject();
			$cookie = $cookies[0]['Name'] . '=' . $cookies[0]['Value'];
			$output->writeln(sprintf('cookie.initialize: <comment>%s</comment>', $cookie));
			
			// Sleep
			$seconds = rand($input->getOption('min-sleep'), $input->getOption('max-sleep'));
			$output->writeln(sprintf('sleep <comment>%s</comment> seconds', $seconds));
			sleep($seconds);
		};
		$this->searcher->getDispatcher()->addListener('cookie.initialize', $initializeCookie);
		
		// Event captcha.download
		$downloadCaptcha = function(Event $e) use(&$output)
		{
			$output->writeln('captcha.download');
		};
		$this->searcher->getDispatcher()->addListener('captcha.download', $downloadCaptcha);
		
		// Event captcha.decode
		$decodeCaptcha = function(GenericEvent $e) use(&$output)
		{
			$output->writeln(sprintf('captcha.decode: <comment>%s</comment>', $e->getSubject()));
		};
		$this->searcher->getDispatcher()->addListener('captcha.decode', $decodeCaptcha);

		// Event search.send
		$sendSearch = function(GenericEvent $e) use(&$output)
		{
			/* @var $response Response */
			$response = $e->getSubject();
			$output->writeln(sprintf('search.send: <comment>%s</comment>', $response->getStatusCode() . ' ' . $response->getReasonPhrase()));
		};
		$this->searcher->getDispatcher()->addListener('search.send', $sendSearch);
		
		// Event error.return
		$returnError = function(GenericEvent $e) use(&$output, &$input)
		{
			$output->writeln(sprintf('error.return: <error>%s</error>', $e->getSubject()));
			
			// Sleep
			$seconds = rand($input->getOption('min-sleep'), $input->getOption('max-sleep'));
			$output->writeln(sprintf('sleep <comment>%s</comment> seconds', $seconds));
			sleep($seconds);
		};
		$this->searcher->getDispatcher()->addListener('error.return', $returnError);
		
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
