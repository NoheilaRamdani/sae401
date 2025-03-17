<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250317165255 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE assignment CHANGE title title VARCHAR(255) NOT NULL, CHANGE due_date due_date DATETIME NOT NULL, CHANGE submission_type submission_type VARCHAR(50) NOT NULL, CHANGE type type VARCHAR(255) NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE assignment CHANGE title title VARCHAR(255) DEFAULT NULL, CHANGE due_date due_date DATETIME DEFAULT NULL, CHANGE submission_type submission_type VARCHAR(50) DEFAULT NULL, CHANGE type type VARCHAR(255) DEFAULT NULL');
    }
}
