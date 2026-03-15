import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import 'react-big-calendar/lib/css/react-big-calendar.css';
import CalendarApp from './components/calendar-app.tsx';
import CalendarPaneApp from './components/calendar-pane-app.tsx';
import { CalendarParentStore } from './components/calendar-parent-store.ts';
import './styles.css';
import type { CalendarConfig } from './types.ts';

const CALENDAR_ROOT_SELECTOR = '.js-post-calendar-root';
const BRICKS_PARENT_SELECTOR = '.post-calendar-element[data-config]';

let observer: MutationObserver | null = null;
const parentStores = new WeakMap<HTMLElement, CalendarParentStore>();

function normalizeView(value: string | undefined): string {
  switch (value) {
    case 'week':
    case 'day':
    case 'agenda':
    case 'year':
      return value;
    case 'month':
    default:
      return 'month';
  }
}

function parseConfig(element: HTMLElement): CalendarConfig {
  const rawConfig = element.dataset.config;

  if (!rawConfig) {
    return {};
  }

  try {
    return {
      ...(JSON.parse(rawConfig) as CalendarConfig),
    };
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
    <StrictMode>
      <CalendarApp config={config} runtime={globalThis.PostCalendarRuntime ?? {}} />
    </StrictMode>
  );

  element.dataset.mounted = 'true';
}

function mountBricksCalendar(parentElement: HTMLElement): void {
  if (parentElement.dataset.mounted === 'true') {
    return;
  }

  const rawConfig = parentElement.dataset.config;
  let config: CalendarConfig = {};

  if (rawConfig) {
    try {
      config = JSON.parse(rawConfig) as CalendarConfig;
    } catch {
      config = {
        error: globalThis.PostCalendarRuntime?.strings?.configParseError ?? 'Unable to parse the calendar configuration.',
      };
    }
  }

  const store = new CalendarParentStore(parentElement, config, globalThis.PostCalendarRuntime ?? {});
  parentStores.set(parentElement, store);

  parentElement.querySelectorAll<HTMLElement>('[data-post-calendar-view-panel]').forEach((paneRoot) => {
    if (paneRoot.dataset.mounted === 'true') {
      return;
    }

    const paneView = normalizeView(paneRoot.dataset.postCalendarViewPanel);
    const root = createRoot(paneRoot);

    root.render(
      <StrictMode>
        <CalendarPaneApp paneView={paneView} store={store} />
      </StrictMode>
    );

    paneRoot.dataset.mounted = 'true';
  });

  parentElement.dataset.mounted = 'true';
}

function mountCalendarsInNode(node: ParentNode): void {
  if (node instanceof HTMLElement && node.matches(CALENDAR_ROOT_SELECTOR)) {
    mountCalendar(node);
  }

  if (node instanceof HTMLElement && node.matches(BRICKS_PARENT_SELECTOR)) {
    mountBricksCalendar(node);
  }

  node.querySelectorAll<HTMLElement>(CALENDAR_ROOT_SELECTOR).forEach(mountCalendar);
  node.querySelectorAll<HTMLElement>(BRICKS_PARENT_SELECTOR).forEach(mountBricksCalendar);
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