<?php
class Controller
{
    protected function view(string $view, array $data = []): void
    {
        extract($data);
        $path = __DIR__ . '/../views/' . $view . '.php';

        if (!file_exists($path)) {
            http_response_code(500);
            echo '<h1>Error</h1><p>La vista solicitada no existe.</p>';
            return;
        }

        require $path;
    }
}
