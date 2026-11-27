<?php

namespace App\Tables;

use Closure;
use Illuminate\Database\Eloquent\Model;

/**
 * An action on the selected rows of any table. The shared endpoint (TableBulkController) checks the
 * permission, loads the selected rows through the table's own query (so a table can never reach rows it
 * does not list) and runs the handler per row. A handler returns null when done, or a short reason
 * (string) when that row is skipped — the same business rules as the single-row action apply.
 */
final class BulkAction
{
    private bool $confirm = false;

    /** For document actions: a GET URL opened with ?ids=… instead of the bulk endpoint. */
    private ?string $href = null;

    private string $variant = 'outline';

    /** @param Closure(Model): (string|null) $handler */
    private function __construct(
        private readonly string $action,
        private readonly string $label,
        private readonly ?string $permission,
        private readonly Closure $handler,
    ) {}

    /** @param Closure(Model): (string|null) $handler */
    public static function make(string $action, string $label, ?string $permission, Closure $handler): self
    {
        return new self($action, $label, $permission, $handler);
    }

    /** Opens a URL with the selected ids (e.g. print the shipping labels of the selected orders). */
    public static function download(string $action, string $label, ?string $permission, string $url): self
    {
        $bulk = new self($action, $label, $permission, fn () => null);
        $bulk->href = $url;

        return $bulk;
    }

    /** Sets a boolean column (e.g. is_active) on every selected row. */
    public static function toggle(string $action, string $label, ?string $permission, string $column, bool $value): self
    {
        return new self($action, $label, $permission, function (Model $model) use ($column, $value): ?string {
            $model->forceFill([$column => $value])->save();

            return null;
        });
    }

    /** Destructive: red button + a confirmation naming the number of rows. */
    public function destructive(): self
    {
        $this->confirm = true;
        $this->variant = 'destructive';

        return $this;
    }

    /** Ask before running (without the destructive styling). */
    public function confirmed(): self
    {
        $this->confirm = true;

        return $this;
    }

    public function name(): string
    {
        return $this->action;
    }

    public function permission(): ?string
    {
        return $this->permission;
    }

    public function run(Model $model): ?string
    {
        return ($this->handler)($model);
    }

    /** @return array{action: string, label: string, confirm: bool, variant: string, href: ?string} */
    public function toArray(): array
    {
        return ['action' => $this->action, 'label' => $this->label, 'confirm' => $this->confirm, 'variant' => $this->variant, 'href' => $this->href];
    }
}
