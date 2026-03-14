import React from 'react';
import { createRoot } from 'react-dom/client';
import 'react-big-calendar/lib/css/react-big-calendar.css';
import CalendarApp from './components/calendar-app.tsx';
import './styles.css';
import type { CalendarConfig } from './types.ts';

const CALENDAR_ROOT_SELECTOR = '.js-post-calendar-root';
const ALLOWED_VIEWS = ['month', 'week', 'day', 'agenda', 'year'] as const;

let observer: MutationObserver | null = null;

function getCalendarScope(element: HTMLElement): HTMLElement | null {
  return element.closest('.post-calendar-element');
}

function normalizeView(value: string | undefined): string | undefined {
  if (!value) {
    return undefined;
  }

  return ALLOWED_VIEWS.includes(value as (typeof ALLOWED_VIEWS)[number]) ? value : undefined;
}

function getOrderedViewPanelNodes(scope: HTMLElement | null): HTMLElement[] {
  if (!scope) {
    return [];
  }

  return Array.from(scope.querySelectorAll<HTMLElement>('.post-calendar-view-panels > .post-calendar-content > [data-post-calendar-view-panel]'));
}

function getExternalViewContentItems(scope: HTMLElement | null): HTMLElement[] {
  if (!scope) {
    return [];
  }

  return Array.from(scope.querySelectorAll<HTMLElement>('.post-calendar-view-panels > .post-calendar-content'));
}

function getViewForContentIndex(scope: HTMLElement | null, index: number): string | undefined {
  const panel = getExternalViewContentItems(scope)[index]?.querySelector<HTMLElement>('[data-post-calendar-view-panel]');

  return normalizeView(panel?.dataset.postCalendarViewPanel);
}

function getExternalViewOrder(scope: HTMLElement | null): string[] {
  return Array.from(new Set(
    getOrderedViewPanelNodes(scope)
    .map((panel) => normalizeView(panel.dataset.postCalendarViewPanel))
    .filter((view): view is string => Boolean(view))
  ));
}

function getAgendaTemplate(scope: HTMLElement | null): string | undefined {
  const agendaPanel = getOrderedViewPanelNodes(scope).find((panel) => normalizeView(panel.dataset.postCalendarViewPanel) === 'agenda');

  if (!agendaPanel || typeof document === 'undefined') {
    return undefined;
  }

  const agendaItem = agendaPanel.querySelector<HTMLElement>('[data-post-calendar-role="agenda-item"]');

  return agendaItem?.outerHTML.trim() || undefined;
}

function parseConfig(element: HTMLElement): CalendarConfig {
  const rawConfig = element.dataset.config;
  const scope = getCalendarScope(element);
  const externalViewOrder = getExternalViewOrder(scope);
  const agendaTemplate = getAgendaTemplate(scope);

  const externalViewConfig = {
    ...(externalViewOrder.length > 0 ? { enabledViews: externalViewOrder } : {}),
  };

  if (!rawConfig) {
    return {
      ...externalViewConfig,
      ...(agendaTemplate ? { agendaTemplate } : {}),
    };
  }

  try {
    const config = JSON.parse(rawConfig) as CalendarConfig;

    const defaultView = normalizeView(config.defaultView);
    const openTabIndex = typeof config.openTab === 'string' ? Number.parseInt(config.openTab, 10) : Number(config.openTab ?? 0);
    const openTabView = Number.isInteger(openTabIndex) && openTabIndex >= 0 ? getViewForContentIndex(scope, openTabIndex) : undefined;
    const resolvedDefaultView = openTabView
      ? openTabView
      : externalViewOrder.length > 0 && (!defaultView || !externalViewOrder.includes(defaultView))
        ? externalViewOrder[0]
        : config.defaultView;

    return {
      ...config,
      ...externalViewConfig,
      ...(resolvedDefaultView ? { defaultView: resolvedDefaultView } : {}),
      ...(agendaTemplate ? { agendaTemplate } : {}),
    };
  } catch {
    return {
      ...externalViewConfig,
      ...(agendaTemplate ? { agendaTemplate } : {}),
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
      <CalendarApp config={config} hostElement={element} runtime={globalThis.PostCalendarRuntime ?? {}} />
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