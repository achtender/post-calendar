import React from 'react';
import { createRoot } from 'react-dom/client';
import 'react-big-calendar/lib/css/react-big-calendar.css';
import CalendarApp from './components/calendar-app.tsx';
import './styles.css';
import type { CalendarConfig } from './types.ts';

const CALENDAR_ROOT_SELECTOR = '.js-post-calendar-root';

let observer: MutationObserver | null = null;

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

function mountCalendarsInNode(node: ParentNode): void {
  if (node instanceof HTMLElement && node.matches(CALENDAR_ROOT_SELECTOR)) {
    mountCalendar(node);
  }

  node.querySelectorAll<HTMLElement>(CALENDAR_ROOT_SELECTOR).forEach(mountCalendar);
}

function observeCalendarRoots(): void {
  if (observer || !document.body) {
    return;
  }

  observer = new MutationObserver((records) => {
    records.forEach((record) => {
      record.addedNodes.forEach((node) => {
        if (!(node instanceof HTMLElement)) {
          return;
        }

        mountCalendarsInNode(node);
      });
    });
  });

  observer.observe(document.body, {
    childList: true,
    subtree: true,
  });
}

function start(): void {
  mountCalendarsInNode(document);
  observeCalendarRoots();
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', start, { once: true });
} else {
  start();
}