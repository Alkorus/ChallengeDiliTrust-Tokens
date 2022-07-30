<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20220727210508 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE token_api ADD est_actif TINYINT(1) NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8D93D64944FB41C9 ON user (courriel)');
        $this->addSql('ALTER TABLE user RENAME INDEX fk_8d93d6492aadbacd TO IDX_8D93D6492AADBACD');
        $this->addSql('ALTER TABLE user RENAME INDEX fk_8d93d6496524603f TO IDX_8D93D6496524603F');
        $this->addSql('ALTER TABLE user RENAME INDEX fk_8d93d649f765f60e TO IDX_8D93D649F765F60E');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE token_api DROP est_actif');
        $this->addSql('DROP INDEX UNIQ_8D93D64944FB41C9 ON user');
        $this->addSql('ALTER TABLE user RENAME INDEX idx_8d93d6496524603f TO FK_8D93D6496524603F');
        $this->addSql('ALTER TABLE user RENAME INDEX idx_8d93d6492aadbacd TO FK_8D93D6492AADBACD');
        $this->addSql('ALTER TABLE user RENAME INDEX idx_8d93d649f765f60e TO FK_8D93D649F765F60E');
    }
}
