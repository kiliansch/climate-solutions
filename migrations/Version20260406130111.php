<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260406130111 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE activities ADD public_token VARCHAR(36) DEFAULT NULL');
        $this->addSql("UPDATE activities SET public_token = gen_random_uuid()::text WHERE public_token IS NULL");
        $this->addSql('ALTER TABLE activities ALTER COLUMN public_token SET NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_B5F1AFE5AE981E3B ON activities (public_token)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX UNIQ_B5F1AFE5AE981E3B');
        $this->addSql('ALTER TABLE activities DROP public_token');
    }
}
