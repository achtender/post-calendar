import React from 'react';
import { createRoot } from 'react-dom/client';
import 'react-big-calendar/lib/css/react-big-calendar.css';
import CalendarApp from './components/calendar-app.tsx';
import './styles.css';
import type { CalendarConfig } from './types.ts';

function parseConfig(element: HTMLElement): CalendarConfig {
  const rawConfig = element.dataset.config;

  if (!rawConfig) {
    return {};
  }

  try {
    return JSON.parse(rawConfig) as CalendarConfig;
  } catch {
    return {
      error: globalThis.PostCalendarRuntime?.strings?.configParseError ?? 'Unable to parse the calendar configuration.',
    };
  }
}

function mountCalendar(element: HTMLElement): void {
  if (element.dataset.mounted === 'true') {
    return;
  }

  const config = parseConfig(element);
  const root = createRoot(element);

  root.render(
    <React.StrictMode>
      <CalendarApp config={config} runtime={globalThis.PostCalendarRuntime ?? {}} />
    </React.StrictMode>
  );

  element.dataset.mounted = 'true';
}

function boot(): void {
  document.querySelectorAll<HTMLElement>('.js-post-calendar-root').forEach(mountCalendar);
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', boot, { once: true });
} else {
  boot();
}