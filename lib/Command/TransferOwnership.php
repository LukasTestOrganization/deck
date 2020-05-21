<?php
declare(strict_types=1);

namespace OCA\Deck\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class TransferOwnership extends Command {

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
        $db = \OC::$server->getDatabaseConnection();

        $output->writeln("Transfer deck entities from $owner to $newOwner");
        $params = [
            'owner' => $owner,
            'newOwner' => $newOwner
        ];

        $output->writeln('update oc_deck_assigned_users');
        $stmt = $db->prepare('UPDATE `oc_deck_assigned_users` SET `participant` = :newOwner WHERE `participant` = :owner');
        $stmt->execute($params);

        $output->writeln('update oc_deck_attachment');
        $stmt = $db->prepare('UPDATE `oc_deck_attachment` SET `created_by` = :newOwner WHERE `created_by` = :owner');
        $stmt->execute($params);

        $output->writeln('update oc_deck_boards');
        $stmt = $db->prepare('UPDATE `oc_deck_boards` SET `owner` = :newOwner WHERE `owner` = :owner');
        $stmt->execute($params);

        $output->writeln('update oc_deck_board_acl');
        $stmt = $db->prepare('UPDATE `oc_deck_board_acl` SET `participant` = :newOwner WHERE `participant` = :owner');
        $stmt->execute($params);

        $output->writeln('update oc_deck_cards');
        $stmt = $db->prepare('UPDATE `oc_deck_cards` SET `owner` = :newOwner WHERE `owner` = :owner');
        $stmt->execute($params);

        $output->writeln("Transfer deck entities from $owner to $newOwner completed");
    }

}
