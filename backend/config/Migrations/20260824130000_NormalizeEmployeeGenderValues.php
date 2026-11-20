<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

final class NormalizeEmployeeGenderValues extends AbstractMigration
{
    public function up(): void
    {
        $this->execute("UPDATE employees SET gender = LOWER(gender) WHERE gender IS NOT NULL");
    }

    public function down(): void
    {
    }
}
