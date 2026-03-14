import React from 'react';
import { createRoot } from 'react-dom/client';
import 'react-big-calendar/lib/css/react-big-calendar.css';
import CalendarApp from '../components/calendar-app.tsx';
import '../styles.css';
import './preview.css';
import type { CalendarConfig, CalendarEventInput, CalendarRuntime } from '../types.ts';

const previewBaseDate = new Date();
const previewYear = previewBaseDate.getFullYear();

function buildFixedPreviewDate(month: number, day: number, hour = 9, minute = 0): string {
  return new Date(Date.UTC(previewYear, month - 1, day, hour, minute)).toISOString();
}

function buildRelativePreviewDate(dayOffset: number, hour = 9, minute = 0): string {
  const nextDate = new Date(previewBaseDate);
  nextDate.setHours(hour, minute, 0, 0);
  nextDate.setDate(nextDate.getDate() + dayOffset);
  return nextDate.toISOString();
}

const previewEvents: CalendarEventInput[] = [
  {
    id: 101,
    title: 'River path bird walk',
    start: buildFixedPreviewDate(1, 22, 8, 30),
    end: buildFixedPreviewDate(1, 22, 11, 30),
    allDay: false,
    url: '#event-101',
    excerpt: 'A slow winter walk with binoculars, hot tea, and a notebook for sightings.',
    tags: ['Outdoors'],
  },
  {
    id: 102,
    title: 'Firelight soup night',
    start: buildFixedPreviewDate(1, 30, 17, 0),
    end: buildFixedPreviewDate(1, 30, 20, 30),
    allDay: false,
    url: '#event-102',
    excerpt: 'Bring a pot, a loaf, or just a bowl and linger by the stove for a while.',
    tags: ['Food'],
  },
  {
    id: 1,
    title: 'Greenhouse cleanup day',
    start: buildFixedPreviewDate(3, 16, 10, 0),
    end: buildFixedPreviewDate(3, 16, 15, 30),
    allDay: false,
    url: '#event-1',
    excerpt: 'Sweep the benches, wash the glass, and sort seed trays before the first planting.',
    tags: ['Garden'],
  },
  {
    id: 2,
    title: 'Window herb swap',
    start: buildFixedPreviewDate(3, 18, 12, 0),
    end: buildFixedPreviewDate(3, 18, 16, 0),
    allDay: true,
    url: '#event-2',
    excerpt: 'Trade cuttings, jars, and little labeled pots for kitchens that need more green.',
    tags: ['Swap'],
  },
  {
    id: 3,
    title: 'Late porch singalong',
    start: buildFixedPreviewDate(5, 24, 18, 0),
    end: buildFixedPreviewDate(5, 24, 21, 30),
    allDay: false,
    url: '#event-3',
    excerpt: 'Print lyric sheets, string up a light or two, and keep the loud songs for dusk.',
    tags: ['Music'],
  },
  {
    id: 7,
    title: 'Creekside sketch picnic',
    start: buildFixedPreviewDate(7, 5, 13, 0),
    end: buildFixedPreviewDate(7, 5, 18, 0),
    allDay: false,
    url: '#event-7',
    excerpt: 'Blankets, pencils, and a spot in the shade for anyone who wants to draw slowly.',
    tags: ['Art'],
  },
  {
    id: 8,
    title: 'Night garden lantern walk',
    start: buildFixedPreviewDate(9, 14, 19, 0),
    end: buildFixedPreviewDate(9, 14, 21, 0),
    allDay: false,
    url: '#event-8',
    excerpt: 'A quiet loop through the beds after dark with paper lanterns and warm cider.',
    tags: ['Community'],
  },
  {
    id: 105,
    title: 'Coat mending table',
    start: buildFixedPreviewDate(11, 9, 14, 0),
    end: buildFixedPreviewDate(11, 9, 18, 30),
    allDay: false,
    url: '#event-105',
    excerpt: 'Patch elbows, resew buttons, and trade spare thread while the kettle stays on.',
    tags: ['Craft'],
  },
  {
    id: 106,
    title: 'Longest night potluck',
    start: buildFixedPreviewDate(12, 21, 17, 0),
    end: buildFixedPreviewDate(12, 21, 22, 0),
    allDay: false,
    url: '#event-106',
    excerpt: 'A long table, borrowed chairs, and the kind of meal that starts before sunset.',
    tags: ['Gathering'],
  },
  {
    id: 201,
    title: 'Open studio morning',
    start: buildRelativePreviewDate(0, 9, 0),
    end: buildRelativePreviewDate(0, 14, 30),
    allDay: false,
    url: '#event-201',
    excerpt: 'Tables stay out for painting, stitching, and half-finished projects that need a few more hours.',
    tags: ['Today', 'Art'],
  },
  {
    id: 202,
    title: 'Tea and mending circle',
    start: buildRelativePreviewDate(0, 11, 0),
    end: buildRelativePreviewDate(0, 17, 0),
    allDay: false,
    url: '#event-202',
    excerpt: 'Bring a torn sleeve, a loose hem, or just sit with tea while the room hums around you.',
    tags: ['Today', 'Craft'],
  },
  {
    id: 203,
    title: 'Slow lunch on the steps',
    start: buildRelativePreviewDate(0, 12, 30),
    end: buildRelativePreviewDate(0, 15, 30),
    allDay: false,
    url: '#event-203',
    excerpt: 'A long overlapping midday hangout with blankets, fruit, and nowhere urgent to be.',
    tags: ['Today', 'Food'],
  },
  {
    id: 204,
    title: 'Porch music rehearsal',
    start: buildRelativePreviewDate(-1, 15, 0),
    end: buildRelativePreviewDate(-1, 19, 30),
    allDay: false,
    url: '#event-204',
    excerpt: 'A loose run-through with chairs in a circle and a little spillover into the evening.',
    tags: ['Music'],
  },
  {
    id: 205,
    title: 'Patchwork picnic setup',
    start: buildRelativePreviewDate(1, 10, 0),
    end: buildRelativePreviewDate(1, 18, 0),
    allDay: false,
    url: '#event-205',
    excerpt: 'A long next-day setup block with overlapping arrivals, borrowed tables, and baskets to sort.',
    tags: ['Gathering'],
  },
];

const previewConfig: CalendarConfig = {
  defaultView: 'year',
  enabledViews: ['year', 'month', 'week', 'day', 'agenda'],
  showToolbar: true,
  agendaRangeMode: 'upcoming-window',
  agendaRangeMonths: 3,
  postTypes: ['event'],
};

const previewRuntime: CalendarRuntime = {
  locale: 'en-US',
  previewEvents,
  strings: {
    allDay: 'All-day',
    agenda: 'Agenda',
    back: 'Back',
    calendarViews: 'Calendar views',
    configParseError: 'Unable to parse the calendar configuration.',
    date: 'Date',
    day: 'Day',
    event: 'Event',
    loadError: 'Unable to load calendar events right now.',
    missingApiUrl: 'The calendar API URL is missing.',
    month: 'Month',
    next: 'Next',
    noEvents: 'No preview events are scheduled in this range.',
    showMore: 'more',
    showMoreEventsForMonth: 'Show %1$s more events for %2$s',
    time: 'Time',
    today: 'Today',
    week: 'Week',
    year: 'Year',
  },
};

const previewRoot = document.getElementById('preview-root');

if (previewRoot) {
  createRoot(previewRoot).render(
    <React.StrictMode>
      <CalendarApp config={previewConfig} hostElement={previewRoot} runtime={previewRuntime} />
    </React.StrictMode>
  );
}