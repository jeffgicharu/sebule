{!! $heading !!}

@foreach ($lines as $line)
{!! $line !!}

@endforeach
{!! $action !!}: {!! $url !!}

{!! $why !!}

Sebule, {!! config('store.showroom.street') !!}, {!! config('store.showroom.city') !!}
