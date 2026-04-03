<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260403135219 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE booking_requests ALTER created_at TYPE TIMESTAMP(0) WITH TIME ZONE USING created_at AT TIME ZONE \'UTC\'');
        $this->addSql('ALTER TABLE booking_requests ALTER selected_date TYPE TIMESTAMP(0) WITH TIME ZONE USING selected_date AT TIME ZONE \'UTC\'');
        $this->addSql('ALTER TABLE calendars ALTER created_at TYPE TIMESTAMP(0) WITH TIME ZONE USING created_at AT TIME ZONE \'UTC\'');
        $this->addSql('ALTER TABLE invitations ALTER expires_at TYPE TIMESTAMP(0) WITH TIME ZONE USING expires_at AT TIME ZONE \'UTC\'');
        $this->addSql('ALTER TABLE invitations ALTER accepted_at TYPE TIMESTAMP(0) WITH TIME ZONE USING accepted_at AT TIME ZONE \'UTC\'');
        $this->addSql('ALTER TABLE notifications ALTER read_at TYPE TIMESTAMP(0) WITH TIME ZONE USING read_at AT TIME ZONE \'UTC\'');
        $this->addSql('ALTER TABLE notifications ALTER created_at TYPE TIMESTAMP(0) WITH TIME ZONE USING created_at AT TIME ZONE \'UTC\'');
        $this->addSql('DROP INDEX uniq_slot_unavailability_slot_date');
        $this->addSql('ALTER TABLE slot_unavailabilities ALTER blocked_date TYPE TIMESTAMP(0) WITH TIME ZONE USING blocked_date AT TIME ZONE \'UTC\'');
        $this->addSql('CREATE UNIQUE INDEX uniq_slot_unavailability_slot_date ON slot_unavailabilities (slot_id, blocked_date)');
        $this->addSql('ALTER TABLE slots ALTER start_at TYPE TIMESTAMP(0) WITH TIME ZONE USING start_at AT TIME ZONE \'UTC\'');
        $this->addSql('ALTER TABLE slots ALTER end_at TYPE TIMESTAMP(0) WITH TIME ZONE USING end_at AT TIME ZONE \'UTC\'');
        $this->addSql('ALTER TABLE slots ALTER created_at TYPE TIMESTAMP(0) WITH TIME ZONE USING created_at AT TIME ZONE \'UTC\'');
        $this->addSql('ALTER TABLE unavailabilities ALTER start_at TYPE TIMESTAMP(0) WITH TIME ZONE USING start_at AT TIME ZONE \'UTC\'');
        $this->addSql('ALTER TABLE unavailabilities ALTER end_at TYPE TIMESTAMP(0) WITH TIME ZONE USING end_at AT TIME ZONE \'UTC\'');
        $this->addSql('ALTER TABLE users ALTER created_at TYPE TIMESTAMP(0) WITH TIME ZONE USING created_at AT TIME ZONE \'UTC\'');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE booking_requests ALTER selected_date TYPE TIMESTAMP(0) WITHOUT TIME ZONE USING selected_date AT TIME ZONE \'UTC\'');
        $this->addSql('ALTER TABLE booking_requests ALTER created_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE USING created_at AT TIME ZONE \'UTC\'');
        $this->addSql('ALTER TABLE calendars ALTER created_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE USING created_at AT TIME ZONE \'UTC\'');
        $this->addSql('ALTER TABLE invitations ALTER expires_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE USING expires_at AT TIME ZONE \'UTC\'');
        $this->addSql('ALTER TABLE invitations ALTER accepted_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE USING accepted_at AT TIME ZONE \'UTC\'');
        $this->addSql('ALTER TABLE notifications ALTER read_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE USING read_at AT TIME ZONE \'UTC\'');
        $this->addSql('ALTER TABLE notifications ALTER created_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE USING created_at AT TIME ZONE \'UTC\'');
        $this->addSql('DROP INDEX uniq_slot_unavailability_slot_date');
        $this->addSql('ALTER TABLE slot_unavailabilities ALTER blocked_date TYPE TIMESTAMP(0) WITHOUT TIME ZONE USING blocked_date AT TIME ZONE \'UTC\'');
        $this->addSql('CREATE UNIQUE INDEX uniq_slot_unavailability_slot_date ON slot_unavailabilities (slot_id, blocked_date)');
        $this->addSql('ALTER TABLE slots ALTER start_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE USING start_at AT TIME ZONE \'UTC\'');
        $this->addSql('ALTER TABLE slots ALTER end_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE USING end_at AT TIME ZONE \'UTC\'');
        $this->addSql('ALTER TABLE slots ALTER created_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE USING created_at AT TIME ZONE \'UTC\'');
        $this->addSql('ALTER TABLE unavailabilities ALTER start_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE USING start_at AT TIME ZONE \'UTC\'');
        $this->addSql('ALTER TABLE unavailabilities ALTER end_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE USING end_at AT TIME ZONE \'UTC\'');
        $this->addSql('ALTER TABLE users ALTER created_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE USING created_at AT TIME ZONE \'UTC\'');
    }
}
