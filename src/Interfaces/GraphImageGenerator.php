<?php

namespace App\Interfaces;

use Graphp\Graph\Graph;

interface GraphImageGenerator
{
    public function generateGraph(array $issues): Graph;
    public function createImageFile(Graph $graph): string;
}
