<?php

namespace EasyMalt;

function renderView(string $viewName, array $view): void {
    require __DIR__ . '/' . $viewName . '.php';
}
