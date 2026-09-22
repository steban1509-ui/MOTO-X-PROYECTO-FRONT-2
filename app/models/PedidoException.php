<?php
final class PedidoException extends RuntimeException
{
    private array $productosNoDisponibles;
    private array $productosSinStock;

    public function __construct(string $mensaje, array $productosNoDisponibles = [], array $productosSinStock = [])
    {
        parent::__construct($mensaje);
        $this->productosNoDisponibles = $productosNoDisponibles;
        $this->productosSinStock      = $productosSinStock;
    }

    public function productosNoDisponibles(): array
    {
        return $this->productosNoDisponibles;
    }

    public function productosSinStock(): array
    {
        return $this->productosSinStock;
    }
}
