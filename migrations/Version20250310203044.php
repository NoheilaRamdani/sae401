<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250310203044 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE delegate (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, group_id INT NOT NULL, start_date DATE DEFAULT NULL, end_date DATE DEFAULT NULL, is_active TINYINT(1) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, INDEX IDX_5F8662FEA76ED395 (user_id), INDEX IDX_5F8662FEFE54D947 (group_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE delegate ADD CONSTRAINT FK_5F8662FEA76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE delegate ADD CONSTRAINT FK_5F8662FEFE54D947 FOREIGN KEY (group_id) REFERENCES `group` (id)');
        $this->addSql('ALTER TABLE assignment ADD type VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE delegate DROP FOREIGN KEY FK_5F8662FEA76ED395');
        $this->addSql('ALTER TABLE delegate DROP FOREIGN KEY FK_5F8662FEFE54D947');
        $this->addSql('DROP TABLE delegate');
        $this->addSql('ALTER TABLE assignment DROP type');
    }
}
