@extends('_layouts.master')

@php
    use Carbon\Carbon;

    $today = Carbon::today();
    $futureEvents = $events->filter(function ($event) use ($today) {
        return Carbon::parse($event->date)->greaterThanOrEqualTo($today);
    });
@endphp

@section('contents')
    <p class="eyelet margin_top_0">
        {{ $page->community->description }}
    </p>
    @if ($futureEvents->isEmpty())
        <h2 class="centered orange scream">
            Último meetup: <span>{{ date('d/m/Y', (int) $events->first()->date) }}, {{ $events->first()->start }}h</span>
        </h2>
        <div class="clearfix">
            <section class="content single">
                <h2><a href="{{ $events->first()->meetup }}">{{ $events->first()->title }}</a></h2>
                {!! $events->first()->getContent() !!}
            </section>
        </div>
    </div>
    @else
        <h2 class="centered orange scream">
            Próximo meetup: <span>{{ date('d/m/Y', (int) $futureEvents->first()->date) }}, {{ $futureEvents->first()->start }}h</span>
        </h2>
        <div class="clearfix">
            <section class="content single">
                <h2><a href="{{ $futureEvents->first()->meetup }}">{{ $futureEvents->first()->title }}</a></h2>
                {!! $futureEvents->first()->getContent() !!}
            </section>
        </div>
    @endif
@endsection
