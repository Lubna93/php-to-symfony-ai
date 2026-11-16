<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261116204922 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE migration_project ADD owner_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE migration_project ADD CONSTRAINT FK_CB9441357E3C61F9 FOREIGN KEY (owner_id) REFERENCES user (id)');
        $this->addSql('CREATE INDEX IDX_CB9441357E3C61F9 ON migration_project (owner_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE migration_project DROP FOREIGN KEY FK_CB9441357E3C61F9');
        $this->addSql('DROP INDEX IDX_CB9441357E3C61F9 ON migration_project');
        $this->addSql('ALTER TABLE migration_project DROP owner_id');
    }
}
