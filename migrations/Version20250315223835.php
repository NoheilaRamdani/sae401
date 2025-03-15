<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250315223835 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE suggestion ADD assignment_id INT NOT NULL, ADD suggested_by_id INT NOT NULL, ADD message LONGTEXT NOT NULL, ADD created_at DATETIME NOT NULL');
        $this->addSql('ALTER TABLE suggestion ADD CONSTRAINT FK_DD80F31BD19302F8 FOREIGN KEY (assignment_id) REFERENCES assignment (id)');
        $this->addSql('ALTER TABLE suggestion ADD CONSTRAINT FK_DD80F31B66290AB1 FOREIGN KEY (suggested_by_id) REFERENCES user (id)');
        $this->addSql('CREATE INDEX IDX_DD80F31BD19302F8 ON suggestion (assignment_id)');
        $this->addSql('CREATE INDEX IDX_DD80F31B66290AB1 ON suggestion (suggested_by_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE suggestion DROP FOREIGN KEY FK_DD80F31BD19302F8');
        $this->addSql('ALTER TABLE suggestion DROP FOREIGN KEY FK_DD80F31B66290AB1');
        $this->addSql('DROP INDEX IDX_DD80F31BD19302F8 ON suggestion');
        $this->addSql('DROP INDEX IDX_DD80F31B66290AB1 ON suggestion');
        $this->addSql('ALTER TABLE suggestion DROP assignment_id, DROP suggested_by_id, DROP message, DROP created_at');
    }
}
