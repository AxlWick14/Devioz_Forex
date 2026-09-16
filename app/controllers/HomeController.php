<?php
require_once __DIR__ . '/../core/Controller.php';

class HomeController extends Controller
{
    public function index(): void
    {
        $this->view('public/home', ['titulo' => 'Inicio | Forex System']);
    }

    public function mercado(): void
    {
        $this->view('public/mercado', ['titulo' => 'Mercado | Forex System']);
    }

    public function calculadora(): void
    {
        $this->view('public/calculadora', [
            'titulo' => 'Calculadora | Forex System',
            'marketRefreshMs' => MARKET_REFRESH_MS,
        ]);
    }

    public function historial(): void
    {
        $this->view('public/historial', ['titulo' => 'Historial | Forex System']);
    }
}
