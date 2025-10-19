@extends('_layouts.master')

@section('contents')
    <p class="eyelet margin_top_0 centered">
        El boletín mensual recopila las novedades más relevantes del ecosistema PHP al cierre de cada mes,
        curadas a partir de <a href="https://www.phpweekly.com/archive/latest.html" target="_blank" rel="noopener">PHP Weekly</a>.
    </p>

    <div class="margin_bottom_20">
        @foreach ($news as $entry)
            <div class="clearfix">
                <section class="content single">
                    <h2 class="centered">{{ $entry->title }}</h2>
                    {!! $entry->getContent() !!}
                </section>
            </div>
        @endforeach
    </div>
@endsection
