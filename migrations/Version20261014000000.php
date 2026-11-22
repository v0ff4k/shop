<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Create messenger_messages table for Messenger failed transport.
 */
final class Version20261014000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create messenger_messages table for failed message transport';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('
            CREATE TABLE IF NOT EXISTS messenger_messages (
                id BIGSERIAL PRIMARY KEY,
                body TEXT NOT NULL,
                headers TEXT NOT NULL,
                queue_name VARCHAR(190) NOT NULL,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                available_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                delivered_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL
            )
        ');

        $this->addSql('CREATE INDEX IF NOT EXISTS idx_messenger_messages_queue ON messenger_messages(queue_name)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_messenger_messages_available ON messenger_messages(available_at)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_messenger_messages_delivered ON messenger_messages(delivered_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS messenger_messages');
    }

    public function isTransactional(): bool
    {
        return true;
    }
}
