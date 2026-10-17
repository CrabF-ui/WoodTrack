@extends('layouts.app')

@section('title', 'Pedidos eliminados')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0">Pedidos eliminados</h1>
        <a href="{{ route('pedidos.index') }}" class="btn btn-outline-secondary btn-sm">Volver a pedidos</a>
    </div>

    <div class="card shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Cliente</th>
                        <th class="text-end">Total</th>
                        <th>Fecha de eliminación</th>
                        <th class="text-end">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($pedidos as $pedido)
                        <tr>
                            <td>{{ $pedido->id }}</td>
                            <td>{{ $pedido->cliente?->nombre ?? '—' }}</td>
                            <td class="text-end">${{ number_format((float) $pedido->total, 2) }}</td>
                            <td>{{ $pedido->deleted_at?->format('d/m/Y H:i') }}</td>
                            <td class="text-end">
                                <form method="POST" action="{{ route('pedidos.restore', $pedido->id) }}">
                                    @csrf
                                    <button type="submit" class="btn btn-success btn-sm">Restaurar</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center text-muted py-4">No hay pedidos en la papelera.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
