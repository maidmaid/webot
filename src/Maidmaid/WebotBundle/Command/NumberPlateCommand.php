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
		$this->searcher->getDispatcher()->addListener('cookie.initialize', function(GenericEvent $e) use(&$output, &$input) {
			$cookies = $e->getSubject();
			$cookie = $cookies[0]['Name'] . '=' . $cookies[0]['Value'];
			$output->writeln(sprintf('cookie.initialize: <comment>%s</comment>', $cookie));
			
			// Sleep
			$seconds = rand($input->getOption('min-sleep'), $input->getOption('max-sleep'));
			$output->writeln(sprintf('sleep <comment>%s</comment> seconds', $seconds));
			sleep($seconds);
		});
		
		// Event captcha.download
		$this->searcher->getDispatcher()->addListener('captcha.download', function(Event $e) use(&$output) {
			$output->writeln('captcha.download');
		});
		
		// Event captcha.decode
		$this->searcher->getDispatcher()->addListener('captcha.decode', function(GenericEvent $e) use(&$output) {
			$output->writeln(sprintf('captcha.decode: <comment>%s</comment>', $e->getSubject()));
		});

		// Event search.send
		$this->searcher->getDispatcher()->addListener('search.send', function(GenericEvent $e) use(&$output) {
			/* @var $response Response */
			$response = $e->getSubject();
			$output->writeln(sprintf('search.send: <comment>%s</comment>', $response->getStatusCode() . ' ' . $response->getReasonPhrase()));
		});
		
		// Event error.return
		$this->searcher->getDispatcher()->addListener('error.return', function(GenericEvent $e) use(&$output, &$input) {
			$output->writeln(sprintf('error.return: <error>%s</error>', $e->getSubject()));
			
			// Sleep
			$seconds = rand($input->getOption('min-sleep'), $input->getOption('max-sleep'));
			$output->writeln(sprintf('sleep <comment>%s</comment> seconds', $seconds));
			sleep($seconds);
		});
		
		// Search
		$numberplate = rand(1, 99999);
		$output->writeln(sprintf('Search for: <question>%s</question>', $numberplate));
		$data = $this->searcher->search($numberplate);
		
		// Result
		$output->writeln(sprintf('Search for: <question>%s</question>', $numberplate));
		if(empty($data))
		{
			$output->writeln(sprintf('<info>%s</info>', $this->searcher->getLastError()));
		}
		else
		{
			$table = new Table($output);
			$table->setHeaders(array_keys($data));
			$table->addRow($data);
			$table->render();
		}
		
		$this->execute($input, $output);
    }
}
