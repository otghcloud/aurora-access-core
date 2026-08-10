<?php

declare(strict_types=1);

namespace OTGH\AccessControl\Core\Services\AccessControl;

class DiagnosticsNavigationRegistry
{
    /**
     * @var array<string,array{route:string,label:string,order:int}>
     */
    private array $items = [];

    public function register(string $routeName, string $label, int $order = 100): void
    {
        $route = trim($routeName);
        $text = trim($label);

        if ($route === '' || $text === '') {
            return;
        }

        $this->items[$route] = [
            'route' => $route,
            'label' => $text,
            'order' => $order,
        ];
    }

    /**
     * @return array<int,array{route:string,label:string,order:int}>
     */
    public function all(): array
    {
        $items = array_values($this->items);

        usort($items, static function (array $a, array $b): int {
            $byOrder = $a['order'] <=> $b['order'];

            if ($byOrder !== 0) {
                return $byOrder;
            }

            return strcmp($a['label'], $b['label']);
        });

        return $items;
    }
}
