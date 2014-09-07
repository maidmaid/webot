<?php

namespace Maidmaid\WebotBundle\Event;

use GuzzleHttp\Message\Response;
use NumberPlate\AbstractSearcherSubscriber;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\EventDispatcher\GenericEvent;

class SearcherSubscriber extends AbstractSearcherSubscriber
{
	/* @var $input InputInterface */
	private $input;
	
	/* @var $output OutputInterface */
	private $output;
	
	public function __construct(InputInterface $input, OutputInterface $output)
	{
		$this->input = $input;
		$this->output = $output;
	}
	
	public function onCookieInitialize(GenericEvent $e)
	{
		$cookies = $e->getSubject();
		$cookie = $cookies[0]['Name'] . '=' . $cookies[0]['Value'];
		$this->output->writeln(sprintf('cookie.initialize: <comment>%s</comment>', $cookie));
		$this->sleep();
	}
	
	public function onCaptchaDownload(Event $e)
	{
		$this->output->writeln('captcha.download');
	}
	
	public function onCaptchaDecode(GenericEvent $e)
	{
		$this->output->writeln(sprintf('captcha.decode: <comment>%s</comment>', $e->getSubject()));
	}
	
	public function onSearchSend(GenericEvent $e)
	{
		/* @var $response Response */
		$response = $e->getSubject();
		$this->output->writeln(sprintf('search.send: <comment>%s</comment>', $response->getStatusCode() . ' ' . $response->getReasonPhrase()));
	}
	
	public function onErrorReturn(GenericEvent $e)
	{
		$this->output->writeln(sprintf('error.return: <error>%s</error>', $e->getSubject()));
		$this->sleep();
	}
	
	public function sleep()
	{
		$seconds = rand($this->input->getOption('min-sleep'), $this->input->getOption('max-sleep'));
		$this->output->writeln(sprintf('sleep <comment>%s</comment> seconds', $seconds));
		sleep($seconds);
	}
}