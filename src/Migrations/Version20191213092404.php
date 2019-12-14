<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20191213092404 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'sqlite', 'Migration can only be executed safely on \'sqlite\'.');

        $this->addSql('ALTER TABLE post ADD COLUMN user_id INTEGER NOT NULL');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'sqlite', 'Migration can only be executed safely on \'sqlite\'.');

        $this->addSql('DROP INDEX UNIQ_5A8A6C8D989D9B62');
        $this->addSql('CREATE TEMPORARY TABLE __temp__post AS SELECT id, name, publication_date, content, slug, image, is_pinned FROM post');
        $this->addSql('DROP TABLE post');
        $this->addSql('CREATE TABLE post (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(255) NOT NULL, publication_date DATETIME NOT NULL, content CLOB NOT NULL, slug VARCHAR(255) NOT NULL, image VARCHAR(255) DEFAULT NULL, is_pinned BOOLEAN NOT NULL)');
        $this->addSql('INSERT INTO post (id, name, publication_date, content, slug, image, is_pinned) SELECT id, name, publication_date, content, slug, image, is_pinned FROM __temp__post');
        $this->addSql('DROP TABLE __temp__post');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_5A8A6C8D989D9B62 ON post (slug)');
    }
}
