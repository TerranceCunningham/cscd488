@extends('redteam.base')

@section('title', 'Red Team Home')

@section('pagecontent')
@if (!$possibleAttacks ?? []->isEmpty())
<h3>Select a method of attack against {{ $blueteam->name }}:</h3>
<form method="POST" action="/redteam/performattack">
    @csrf
    @foreach ($possibleAttacks ?? [] as $attack)
        <?php $asset = Asset::find($inv->asset_id);?>
        <input type="radio" name="result" id="{{ $attack->name }}" value="{{ $attack->name }}">
        <label for="{{ $attack->name }}">{{ $attack->name }}</label>
        <br>
    @endforeach
    @if (!$uselessPossibleAttacks->isEmpty())
        <strong>Opponent doesn't have assets for:</strong><br>
        @foreach ($uselessPossibleAttacks ?? [] as $attack)
        {{ $attack->name }}<br>
        @endforeach
    @endif
    <div class="form-group row mb-0">
        <div class="col-md-8 offset-md-4">
            <button type="submit" class="btn btn-primary">
                Choose Attack
            </button>
        </div>
    </div>
</form>
@else
<strong>You have no methods of attack against {{ $blueteam->name }}</strong><br>
<a href="/redteam/startattack"><button>Pick a Different Team</button></a>
@endif
@endsection
