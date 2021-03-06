@extends('redteam.base')

@section('title', 'Red Team Home')

@section('pagecontent')
    @if (!empty($attackLog))
        <h2>{{ $redteam->name }} attacking {{ $blueteam->name }}</h2>
        <h3>Press a correct button:</h3>
        Each button has a {{ 5 - $attackLog->difficulty }}/5 chance.
        <form method="POST" action="/redteam/minigamecomplete">
        @csrf
            <input type="hidden" name="attackLogID" value="{{ $attackLog->id }}">
            @for ($i = 0; $i < 10; $i++)
                <?php 
                    $randInt = rand(0,5);
                    $val = 0;
                    if($randInt > $attackLog->difficulty){
                        $val = 1;
                    }
                ?>
                <input type="radio" name="result" id="{{ $val }}" value="{{ $val }}">
                <label for="{{ $val }}">{{ $i + 1 }}</label>
                <br>
            @endfor
            <div class="form-group row mb-0">
                <div class="col-md-8 offset-md-4">
                    <button type="submit" class="btn btn-primary">
                        Submit Choice
                    </button>
                </div>
            </div>
        </form>
    @endif
@endsection
