<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250416082745 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE suggestion ADD original_values JSON DEFAULT NULL COMMENT \'(DC2Type:json)\', CHANGE created_at created_at VARCHAR(255) NOT NULL, CHANGE is_processed is_processed TINYINT(1) NOT NULL, CHANGE proposed_changes proposed_changes JSON NOT NULL COMMENT \'(DC2Type:json)\'');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE suggestion DROP original_values, CHANGE proposed_changes proposed_changes JSON DEFAULT NULL COMMENT \'(DC2Type:json)\', CHANGE is_processed is_processed TINYINT(1) DEFAULT 0 NOT NULL, CHANGE created_at created_at DATETIME NOT NULL');
    }
}
