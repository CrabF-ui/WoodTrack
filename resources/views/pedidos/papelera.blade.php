@extends('layouts.admin')

@section('title', 'Pedidos eliminados')
@section('page_title', 'Pedidos eliminados')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <p class="text-muted mb-0">Pedidos que pueden restaurarse.</p>
        <a href="{{ route('pedidos.index') }}" class="btn btn-outline-primary btn-sm">Volver a pedidos</a>
    </div>

    <div class="card">
        <div class="card-header">
            <h2 class="card-title">Papelera</h2>
        </div>
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
                                <form method="POST" action="{{ route('pedidos.restore', $pedido->id) }}" onsubmit="return confirm('¿Restaurar este pedido?')">
                                    @csrf
                                    <button type="submit" class="btn btn-outline-primary btn-sm">Restaurar</button>
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
