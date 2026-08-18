@extends('layouts.app')

@section('content')
    <div class="mb-5">
        <h1 class="text-2xl font-bold">Calendar</h1>
        <p class="text-xs text-slate-500">Jadwal konten dalam tampilan kalender</p>
    </div>

    <div class="panel p-4">
        <div id="calendar"></div>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('vendor/fullcalendar/global.js') }}"></script>
    <script>
        const calendarElement = document.getElementById('calendar');
        const calendarEvents = {{ Illuminate\Support\Js::from($calendarEvents) }};
        const calendarEditable = {{ Illuminate\Support\Js::from(auth()->user()->can('calendar.edit')) }};

        const calendar = new FullCalendar.Calendar(calendarElement, {
            initialView: 'dayGridMonth',
            editable: calendarEditable,
            events: calendarEvents,
            eventDrop: async (info) => {
                const response = await fetch(`/calendar/${info.event.id}`, {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify({ publish_date: info.event.startStr }),
                });

                if (!response.ok) {
                    info.revert();
                }
            },
        });

        calendar.render();
    </script>
@endpush
