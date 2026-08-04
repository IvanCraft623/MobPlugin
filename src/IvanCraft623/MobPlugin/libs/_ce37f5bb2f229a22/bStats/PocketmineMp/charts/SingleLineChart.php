<?php

declare(strict_types=1);

namespace IvanCraft623\MobPlugin\libs\_ce37f5bb2f229a22\bStats\PocketmineMp\charts;

use Closure;

class SingleLineChart extends CustomChart
{
    /** @var Closure(): int */
    private Closure $callable;

    /**
     * @param Closure(): int $callable
     */
    public function __construct(string $chartId, Closure $callable)
    {
        parent::__construct($chartId);
        $this->callable = $callable;
    }

    protected function getChartData(): ?array
    {
        $value = ($this->callable)();
        if ($value === 0) {
            return null;
        }
        return ["value" => $value];
    }
}