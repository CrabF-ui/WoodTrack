@extends('layouts.app')

@section('title', 'Pedidos')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h1 class="h3 mb-1">Pedidos</h1>
            <p class="text-muted mb-0">Gestión de pedidos de venta</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('pedidos.trashed') }}" class="btn btn-outline-secondary btn-sm">Papelera</a>
            <a href="{{ route('pedidos.create') }}" class="btn btn-success">Crear pedido</a>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Cliente</th>
                        <th>Fecha de pedido</th>
                        <th>Fecha de entrega</th>
                        <th>Estado</th>
                        <th class="text-end">Total</th>
                        <th class="text-end">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($pedidos as $pedido)
                        <tr>
                            <td>{{ $pedido->cliente?->nombre ?? '—' }}</td>
                            <td>{{ $pedido->fecha_pedido?->format('d/m/Y') }}</td>
                            <td>{{ $pedido->fecha_entrega?->format('d/m/Y') ?? '—' }}</td>
                            <td><span class="badge text-bg-secondary">{{ Str::headline($pedido->estado) }}</span></td>
                            <td class="text-end">${{ number_format((float) $pedido->total, 2) }}</td>
                            <td class="text-end text-nowrap">
                                <a href="{{ route('pedidos.edit', $pedido->id) }}" class="btn btn-sm btn-outline-primary">Ver / editar</a>
                                <form action="{{ route('pedidos.destroy', $pedido->id) }}" method="POST" class="d-inline" onsubmit="return confirm('¿Eliminar este pedido?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger">Eliminar</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted py-4">Todavía no hay pedidos.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
