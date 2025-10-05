@extends('_layouts.master')

@section('contents')
    @foreach ($events as $event)
        <h3 class="centered past">{{ date('d/m/Y', (int) $event->date) }}, {{ $event->start }}</h3>

        <div class="clearfix">
            <section class="content single">
                <h2><a href="{{ $event->meetup }}">{{ $event->title }}</a></h2>
                {!! $event->getContent() !!}
            </section>
        </div>
    @endforeach
@stop
