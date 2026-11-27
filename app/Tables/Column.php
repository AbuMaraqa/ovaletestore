<?php

namespace App\Tables;

use Closure;

/**
 * One column of a server-defined table (App\Tables\Table), rendered by the shared Vue <ResourceTable>.
 *
 * A column reads its value from a field of the row array (`field`, default = key) and is drawn by type:
 *  text (optionally with an avatar/image, a link, a second line and small chips), badge (value → label +
 *  colour), boolean, money, number, date. The same definition drives sorting and the CSV export.
 */
final class Column
{
    /** @var array<string, mixed> */
    private array $spec;

    private ?Closure $exportUsing = null;

    private function __construct(string $key, string $label, string $type)
    {
        $this->spec = ['key' => $key, 'label' => $label, 'type' => $type, 'field' => $key];
    }

    public static function text(string $key, string $label): self
    {
        return new self($key, $label, 'text');
    }

    /** @param array<string|int, array{0: string, 1: string}> $map value => [label, variant] */
    public static function badge(string $key, string $label, array $map): self
    {
        $column = new self($key, $label, 'badge');
        $column->spec['badges'] = collect($map)->map(fn (array $b) => ['label' => $b[0], 'variant' => $b[1]])->all();

        return $column;
    }

    public static function boolean(string $key, string $label, string $true, string $false): self
    {
        $column = new self($key, $label, 'boolean');
        $column->spec['labels'] = ['true' => $true, 'false' => $false];

        return $column;
    }

    /** Already formatted on the server (Money::format): kept left-to-right and tabular. */
    public static function money(string $key, string $label): self
    {
        return (new self($key, $label, 'money'))->align('end');
    }

    public static function number(string $key, string $label): self
    {
        return (new self($key, $label, 'number'))->align('center');
    }

    /** `field` holds the display text; `title` (optional field) the full date shown on hover. */
    public static function date(string $key, string $label): self
    {
        return new self($key, $label, 'date');
    }

    public function field(string $field): self
    {
        $this->spec['field'] = $field;

        return $this;
    }

    /** Server sort name (true = the key). */
    public function sortable(bool|string $sort = true): self
    {
        $this->spec['sortable'] = $sort;

        return $this;
    }

    public function align(string $align): self
    {
        $this->spec['align'] = $align;

        return $this;
    }

    /** Hidden until the viewer turns it on in the columns menu. */
    public function hidden(): self
    {
        $this->spec['hidden'] = true;

        return $this;
    }

    /** Always shown. */
    public function fixed(): self
    {
        $this->spec['fixed'] = true;

        return $this;
    }

    /** Row field holding a URL: the value becomes a link (Inertia visit). */
    public function link(string $field): self
    {
        $this->spec['href'] = $field;

        return $this;
    }

    /** Row field shown as a smaller second line. */
    public function sub(string $field): self
    {
        $this->spec['sub'] = $field;

        return $this;
    }

    /** Row field with an avatar ({url, initials, color}) shown before the value. */
    public function avatar(string $field): self
    {
        $this->spec['avatar'] = $field;

        return $this;
    }

    /** Row field with an image URL shown as a thumbnail before the value. */
    public function image(string $field): self
    {
        $this->spec['image'] = $field;

        return $this;
    }

    /** Row field with hover text. */
    public function title(string $field): self
    {
        $this->spec['title'] = $field;

        return $this;
    }

    /**
     * Small badges after/under the value, each shown when its row field is truthy.
     *
     * @param  list<array{when: string, label: string, variant?: string, count?: string}>  $chips  `count` appends a row field
     */
    public function chips(array $chips): self
    {
        $this->spec['chips'] = $chips;

        return $this;
    }

    /** Left-to-right value (numbers, codes, emails, phones). */
    public function ltr(): self
    {
        $this->spec['ltr'] = true;

        return $this;
    }

    public function mono(): self
    {
        $this->spec['mono'] = true;

        return $this;
    }

    public function strong(): self
    {
        $this->spec['strong'] = true;

        return $this;
    }

    public function muted(): self
    {
        $this->spec['muted'] = true;

        return $this;
    }

    /** Clamp long text to one line with an ellipsis. */
    public function truncate(): self
    {
        $this->spec['truncate'] = true;

        return $this;
    }

    /** Custom plain-text value for the CSV export. @param Closure(array<string, mixed>): (string|int|float|null) $using */
    public function exportAs(Closure $using): self
    {
        $this->exportUsing = $using;

        return $this;
    }

    public function key(): string
    {
        return $this->spec['key'];
    }

    public function sortName(): ?string
    {
        $sort = $this->spec['sortable'] ?? false;

        return $sort === false ? null : ($sort === true ? $this->spec['key'] : (string) $sort);
    }

    /** @param array<string, mixed> $row */
    public function exportValue(array $row): string
    {
        if ($this->exportUsing) {
            return (string) ($this->exportUsing)($row);
        }
        $value = data_get($row, $this->spec['field']);

        return match ($this->spec['type']) {
            'badge' => (string) ($this->spec['badges'][(string) $value]['label'] ?? $value),
            'boolean' => $value ? $this->spec['labels']['true'] : $this->spec['labels']['false'],
            default => is_array($value) ? implode('، ', array_map('strval', $value)) : (string) ($value ?? ''),
        };
    }

    public function label(): string
    {
        return $this->spec['label'];
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return $this->spec;
    }
}
