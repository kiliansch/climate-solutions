<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260405173116 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE booking_requests ALTER status DROP DEFAULT');
        $this->addSql('ALTER TABLE booking_requests ALTER status SET NOT NULL');
        $this->addSql('ALTER TABLE calendars ALTER display_mode DROP DEFAULT');
        $this->addSql('ALTER TABLE calendars ALTER display_mode SET NOT NULL');
        $this->addSql('DROP INDEX uniq_slot_unavailability_slot_date');
        $this->addSql('ALTER TABLE slots ADD allow_chunked_booking BOOLEAN DEFAULT false NOT NULL');
        $this->addSql('ALTER TABLE slots ADD chunk_cooldown_minutes INT DEFAULT NULL');
        $this->addSql('ALTER TABLE slots ALTER type SET NOT NULL');
        $this->addSql('ALTER TABLE slots ALTER status DROP DEFAULT');
        $this->addSql('ALTER TABLE slots ALTER status SET NOT NULL');
        $this->addSql('ALTER TABLE users ALTER status DROP DEFAULT');
        $this->addSql('ALTER TABLE users ALTER status SET NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE booking_requests ALTER status SET DEFAULT \'pending\'');
        $this->addSql('ALTER TABLE booking_requests ALTER status DROP NOT NULL');
        $this->addSql('ALTER TABLE calendars ALTER display_mode SET DEFAULT \'dayslot\'');
        $this->addSql('ALTER TABLE calendars ALTER display_mode DROP NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX uniq_slot_unavailability_slot_date ON slot_unavailabilities (slot_id, blocked_date)');
        $this->addSql('ALTER TABLE slots DROP allow_chunked_booking');
        $this->addSql('ALTER TABLE slots DROP chunk_cooldown_minutes');
        $this->addSql('ALTER TABLE slots ALTER type DROP NOT NULL');
        $this->addSql('ALTER TABLE slots ALTER status SET DEFAULT \'open\'');
        $this->addSql('ALTER TABLE slots ALTER status DROP NOT NULL');
        $this->addSql('ALTER TABLE users ALTER status SET DEFAULT \'active\'');
        $this->addSql('ALTER TABLE users ALTER status DROP NOT NULL');
    }
}
