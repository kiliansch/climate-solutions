<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260328151500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE booking_requests ADD selected_date TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE booking_requests ALTER status DROP DEFAULT');
        $this->addSql('ALTER TABLE booking_requests ALTER status SET NOT NULL');
        $this->addSql('ALTER TABLE calendars ALTER display_mode DROP DEFAULT');
        $this->addSql('ALTER TABLE calendars ALTER display_mode SET NOT NULL');
        $this->addSql('ALTER TABLE slots ALTER type SET NOT NULL');
        $this->addSql('ALTER TABLE slots ALTER status DROP DEFAULT');
        $this->addSql('ALTER TABLE slots ALTER status SET NOT NULL');
        $this->addSql('ALTER TABLE users ALTER status DROP DEFAULT');
        $this->addSql('ALTER TABLE users ALTER status SET NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE booking_requests DROP selected_date');
        $this->addSql('ALTER TABLE booking_requests ALTER status SET DEFAULT \'pending\'');
        $this->addSql('ALTER TABLE booking_requests ALTER status DROP NOT NULL');
        $this->addSql('ALTER TABLE calendars ALTER display_mode SET DEFAULT \'dayslot\'');
        $this->addSql('ALTER TABLE calendars ALTER display_mode DROP NOT NULL');
        $this->addSql('ALTER TABLE slots ALTER type DROP NOT NULL');
        $this->addSql('ALTER TABLE slots ALTER status SET DEFAULT \'open\'');
        $this->addSql('ALTER TABLE slots ALTER status DROP NOT NULL');
        $this->addSql('ALTER TABLE users ALTER status SET DEFAULT \'active\'');
        $this->addSql('ALTER TABLE users ALTER status DROP NOT NULL');
    }
}
