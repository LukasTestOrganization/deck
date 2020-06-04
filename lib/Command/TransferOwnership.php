<?php

namespace OCA\Deck\Command;

use OCA\Deck\Service\BoardService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class TransferOwnership extends Command {

	protected $boardService;

	public function __construct(BoardService $boardService)
	{
		parent::__construct();

		$this->boardService = $boardService;
	}

	protected function configure() {
		$this
			->setName('deck:transfer-ownership')
			->setDescription('Change owner of deck entities')
			->addArgument(
				'owner',
				InputArgument::REQUIRED,
				'Owner uid'
			)
			->addArgument(
				'newOwner',
				InputArgument::REQUIRED,
				'New owner uid'
			);
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
		$owner = $input->getArgument('owner');
		$newOwner = $input->getArgument('newOwner');

		$output->writeln("Transfer deck entities from $owner to $newOwner");

		$this->boardService->transferOwnership($owner, $newOwner);

		$output->writeln("Transfer deck entities from $owner to $newOwner completed");
	}

}
