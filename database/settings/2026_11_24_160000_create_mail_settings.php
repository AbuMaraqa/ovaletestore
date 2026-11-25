<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('mail.from_name', null);
        $this->migrator->add('mail.from_address', null);
        $this->migrator->add('mail.order_recipients', []);
        $this->migrator->add('mail.notify_new_order', true);
        $this->migrator->add('mail.customer_confirmation', true);
        $this->migrator->add('mail.customer_status_updates', true);
    }

    public function down(): void
    {
        foreach (['from_name', 'from_address', 'order_recipients', 'notify_new_order', 'customer_confirmation', 'customer_status_updates'] as $key) {
            $this->migrator->delete("mail.{$key}");
        }
    }
};
