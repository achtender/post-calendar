import React from 'react';
import { createRoot } from 'react-dom/client';
import 'react-big-calendar/lib/css/react-big-calendar.css';
import CalendarApp from '../components/calendar-app.tsx';
import '../styles.css';
import './preview.css';
import type { CalendarConfig, CalendarEventInput, CalendarRuntime } from '../types.ts';

const previewEvents: CalendarEventInput[] = [
  {
    id: 1,
    title: 'Spring editorial kickoff',
    start: '2026-03-16T09:00:00.000Z',
    end: '2026-03-16T10:30:00.000Z',
    allDay: false,
    url: '#event-1',
    excerpt: 'Align campaign themes, publishing cadence, and ownership for the next six weeks.',
    tags: ['Planning', 'Editorial'],
  },
  {
    id: 2,
    title: 'Content freeze',
    start: '2026-03-18T00:00:00.000Z',
    end: '2026-03-18T23:59:00.000Z',
    allDay: true,
    url: '#event-2',
    excerpt: 'No new post changes while the release branch is validated.',
    tags: ['Release'],
  },
  {
    id: 3,
    title: 'Partner webinar',
    start: '2026-03-24T16:00:00.000Z',
    end: '2026-03-24T17:00:00.000Z',
    allDay: false,
    url: '#event-3',
    excerpt: 'Live session with community partners and Q&A afterward.',
    tags: ['Marketing', 'Live'],
  },
  {
    id: 32,
    title: 'Partner webinar part 2',
    start: '2026-03-24T16:00:00.000Z',
    end: '2026-03-24T17:00:00.000Z',
    allDay: false,
    url: '#event-3',
    excerpt: 'Live session with community partners and Q&A afterward.',
    tags: ['Marketing', 'Live'],
  },
  {
    id: 4,
    title: 'Quarterly roadmap review',
    start: '2026-04-02T13:00:00.000Z',
    end: '2026-04-02T15:00:00.000Z',
    allDay: false,
    url: '#event-4',
    excerpt: 'Review milestones, dependencies, and upcoming launch windows.',
    tags: ['Roadmap'],
  },
  {
    id: 5,
    title: 'Photography day',
    start: '2026-04-10T08:00:00.000Z',
    end: '2026-04-10T18:00:00.000Z',
    allDay: false,
    url: '#event-5',
    excerpt: 'Capture seasonal assets for feature pages and campaign cards.',
    tags: ['Production'],
  },
  {
    id: 6,
    title: 'Publishing retreat',
    start: '2026-04-21T00:00:00.000Z',
    end: '2026-04-23T23:59:00.000Z',
    allDay: true,
    url: '#event-6',
    excerpt: 'Three-day offsite to plan the summer publishing calendar.',
    tags: ['Team', 'Offsite'],
  },
  {
    id: 7,
    title: 'Newsletter send',
    start: '2026-05-05T14:30:00.000Z',
    end: '2026-05-05T15:00:00.000Z',
    allDay: false,
    url: '#event-7',
    excerpt: 'Monthly audience roundup with featured posts and event highlights.',
    tags: ['Email'],
  },
  {
    id: 8,
    title: 'Community roundtable',
    start: '2026-05-14T18:00:00.000Z',
    end: '2026-05-14T19:30:00.000Z',
    allDay: false,
    url: '#event-8',
    excerpt: 'Invite-only discussion with contributors on upcoming initiatives.',
    tags: ['Community'],
  },
];

const previewConfig: CalendarConfig = {
  defaultView: 'month',
  showToolbar: true,
  emptyMessage: 'No preview events are scheduled in this range.',
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
    event: 'Event',
    loadError: 'Unable to load calendar events right now.',
    missingApiUrl: 'The calendar API URL is missing.',
    month: 'Month',
    next: 'Next',
    noEvents: 'No preview events are scheduled in this range.',
    showMore: 'more',
    time: 'Time',
    today: 'Today',
    week: 'Week',
  },
};

function PreviewPage() {
  return (
    <div className="preview-shell">
      <header className="preview-header">
        <div className="preview-title-group">
          <h1>Post Calendar</h1>
          <p className="preview-intro">Dev page for styling the calendar component.</p>
        </div>
      </header>

      <section className="preview-panel preview-panel-calendar" aria-labelledby="calendar-preview-title">
        <CalendarApp config={previewConfig} runtime={previewRuntime} />
      </section>
    </div>
  );
}

const previewRoot = document.getElementById('preview-root');

if (previewRoot) {
  createRoot(previewRoot).render(
    <React.StrictMode>
      <PreviewPage />
    </React.StrictMode>
  );
}