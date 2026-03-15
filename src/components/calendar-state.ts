import { useEffect, useMemo, useState } from 'react';
import { Views } from 'react-big-calendar';
import moment from 'moment';
import type {
  CalendarConfig,
  CalendarEventInput,
  CalendarEventRecord,
  CalendarRange,
  CalendarRuntime,
  CalendarRuntimeStrings,
} from '../types.ts';

const momentLocaleModules = import.meta.glob('../../node_modules/moment/dist/locale/*.js');

export const YEAR_VIEW = 'year';
export const allowedViews = [YEAR_VIEW, Views.MONTH, Views.WEEK, Views.DAY, Views.AGENDA] as const;
export const agendaRangeModes = {
  VISIBLE_RANGE: 'visible-range',
  UPCOMING_WINDOW: 'upcoming-window',
} as const;

const defaultRuntimeStrings: Required<CalendarRuntimeStrings> = {
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
  noEvents: 'No events to display.',
  showMore: 'Show more',
  showMoreEventsForMonth: 'Show %1$s more events for %2$s',
  time: 'Time',
  today: 'Today',
  week: 'Week',
  year: 'Year',
};

export type CalendarView = (typeof allowedViews)[number];
type AgendaRangeMode = (typeof agendaRangeModes)[keyof typeof agendaRangeModes];
export type CalendarRangeInput = Date[] | CalendarRange;

interface UseCalendarStateResult {
  activeViews: readonly CalendarView[];
  agendaLength: number;
  currentDate: Date;
  errorMessage: string;
  events: CalendarEventRecord[];
  handleRangeChange: (range: CalendarRangeInput | null | undefined) => void;
  setCurrentDate: (date: Date) => void;
  setView: (view: CalendarView) => void;
  surfaceClassName: string;
  view: CalendarView;
}

export function getRuntimeStrings(runtime: CalendarRuntime): Required<CalendarRuntimeStrings> {
  return {
    ...defaultRuntimeStrings,
    ...(runtime.strings ?? {}),
  };
}

export function resolveActiveViews(enabledViews?: string[]): readonly CalendarView[] {
  if (!Array.isArray(enabledViews) || enabledViews.length === 0) {
    return allowedViews;
  }

  const filtered = allowedViews.filter((v) => enabledViews.includes(v));

  return filtered.length > 0 ? filtered : allowedViews;
}

export function normalizeView(view?: string): CalendarView {
  return allowedViews.includes(view as CalendarView) ? (view as CalendarView) : Views.MONTH;
}

export function resolveRange(range: CalendarRangeInput | null | undefined): CalendarRange | null {
  if (Array.isArray(range) && range.length > 0) {
    return {
      start: range[0],
      end: range[range.length - 1],
    };
  }

  if (isCalendarRange(range)) {
    return {
      start: range.start,
      end: range.end,
    };
  }

  return null;
}

export function getEventClassName(start: Date, currentDate: Date, view: CalendarView): string | undefined {
  if (view !== Views.MONTH) {
    return undefined;
  }

  return moment(start).isSame(currentDate, 'month') ? undefined : 'is-outside-current-month';
}

export function useCalendarLocale(locale?: string): string | undefined {
  const [culture, setCulture] = useState<string | undefined>(() => normalizeCulture(locale));

  useEffect(() => {
    let cancelled = false;

    void ensureMomentLocale(locale).then((resolvedCulture) => {
      if (!cancelled) {
        setCulture(resolvedCulture);
      }
    });

    return () => {
      cancelled = true;
    };
  }, [locale]);

  return culture;
}

export function useCalendarState(
  config: CalendarConfig,
  runtime: CalendarRuntime,
  strings: Required<CalendarRuntimeStrings>
): UseCalendarStateResult {
  const previewEvents = useMemo(() => getPreviewEvents(runtime.previewEvents), [runtime.previewEvents]);
  const [events, setEvents] = useState<CalendarEventRecord[]>([]);
  const [errorMessage, setErrorMessage] = useState<string>(config.error ?? '');
  const activeViews = useMemo(() => resolveActiveViews(config.enabledViews), [config.enabledViews]);
  const [view, setView] = useState<CalendarView>(() => {
    const initialViews = resolveActiveViews(config.enabledViews);
    const normalized = normalizeView(config.defaultView);
    return initialViews.includes(normalized) ? normalized : (initialViews[0] ?? Views.MONTH);
  });
  const [currentDate, setCurrentDate] = useState<Date>(() => new Date());
  const [activeRange, setActiveRange] = useState<CalendarRange | null>(null);

  const agendaRangeMode = normalizeAgendaRangeMode(config.agendaRangeMode);
  const agendaRangeMonths = normalizePositiveInteger(config.agendaRangeMonths, 3);
  const agendaWindow = useMemo(() => calculateAgendaWindow(currentDate, agendaRangeMonths), [agendaRangeMonths, currentDate]);
  const fallbackRange = useMemo(() => calculateFallbackRange(view, currentDate), [currentDate, view]);
  const effectiveRange = useMemo(() => {
    if (view === Views.AGENDA && agendaRangeMode === agendaRangeModes.UPCOMING_WINDOW) {
      return {
        start: agendaWindow.start,
        end: agendaWindow.end,
      };
    }

    if (view === YEAR_VIEW) {
      return calculateYearRange(currentDate);
    }

    return activeRange ?? fallbackRange;
  }, [activeRange, agendaRangeMode, agendaWindow.end, agendaWindow.start, currentDate, fallbackRange, view]);
  const requestUrl = useMemo(() => buildRequestUrl(config, runtime, effectiveRange), [config, effectiveRange, runtime]);

  useEffect(() => {
    if (previewEvents) {
      setEvents(previewEvents);
      setErrorMessage(config.error ?? '');

      return undefined;
    }

    const controller = new AbortController();

    async function loadEvents(): Promise<void> {
      if (!runtime.restUrl) {
      setErrorMessage(strings.missingApiUrl);

        return;
      }

      try {
        const response = await fetch(requestUrl, {
          headers: {
            'X-WP-Nonce': runtime.restNonce ?? '',
          },
        signal: controller.signal,
        });

        if (!response.ok) {
          throw new Error(`Request failed with status ${response.status}`);
        }

        const payload = (await response.json()) as CalendarEventInput[];

        setEvents(normalizeEvents(Array.isArray(payload) ? payload : []));
        setErrorMessage('');
        } catch (error) {
        if (error instanceof DOMException && error.name === 'AbortError') {
          return;
        }

        setErrorMessage(strings.loadError);
      }
    }

      void loadEvents();

    return () => {
        controller.abort();
    };
  }, [config.error, previewEvents, requestUrl, runtime.restNonce, runtime.restUrl, strings.loadError, strings.missingApiUrl]);

  return {
    activeViews,
    agendaLength: view === Views.AGENDA && agendaRangeMode === agendaRangeModes.UPCOMING_WINDOW ? agendaWindow.length : 30,
    currentDate,
    errorMessage,
    events,
    handleRangeChange: (range) => {
      if ((view === Views.AGENDA && agendaRangeMode === agendaRangeModes.UPCOMING_WINDOW) || view === YEAR_VIEW) {
        return;
      }

      const resolvedRange = resolveRange(range);

      if (resolvedRange) {
        setActiveRange(resolvedRange);
      }
    },
    setCurrentDate,
    setView,
    surfaceClassName: `post-calendar-surface${view === Views.AGENDA ? ' is-agenda-view' : ''}${view === YEAR_VIEW ? ' is-year-view' : ''}`,
    view,
  };
}

function normalizeCulture(locale?: string): string | undefined {
  if (!locale) {
    return undefined;
  }

  return locale.replace(/_/g, '-');
}

function setMomentLocale(locale?: string): void {
  if (!locale) {
    return;
  }

  const normalizedLocale = locale.replace(/_/g, '-').toLowerCase();
  const baseLocale = normalizedLocale.split('-')[0];

  moment.locale([normalizedLocale, baseLocale]);
}

async function ensureMomentLocale(locale?: string): Promise<string | undefined> {
  if (!locale) {
    return undefined;
  }

  const normalizedLocale = locale.replace(/_/g, '-').toLowerCase();
  const baseLocale = normalizedLocale.split('-')[0];
  const localeCandidates = [normalizedLocale, baseLocale];

  for (const localeCandidate of localeCandidates) {
    const loader = momentLocaleModules[`../../node_modules/moment/dist/locale/${localeCandidate}.js`];

    if (!loader) {
      continue;
    }

    await loader();
    setMomentLocale(localeCandidate);

    return normalizeCulture(localeCandidate);
  }

  setMomentLocale(locale);

  return normalizeCulture(locale);
}

export function normalizeAgendaRangeMode(mode?: string): AgendaRangeMode {
  return mode === agendaRangeModes.UPCOMING_WINDOW ? agendaRangeModes.UPCOMING_WINDOW : agendaRangeModes.VISIBLE_RANGE;
}

export function normalizePositiveInteger(value: number | string | undefined, fallback: number): number {
  const parsedValue = Number.parseInt(String(value), 10);

  return Number.isFinite(parsedValue) && parsedValue > 0 ? parsedValue : fallback;
}

export function calculateAgendaWindow(date: Date, monthCount: number): CalendarRange & { length: number } {
  const windowStart = moment(date).startOf('day');
  const windowEnd = windowStart.clone().add(monthCount, 'months').endOf('day');

  return {
    start: windowStart.toDate(),
    end: windowEnd.toDate(),
    length: Math.max(1, windowEnd.diff(windowStart, 'days') + 1),
  };
}

export function calculateFallbackRange(view: CalendarView, date: Date): CalendarRange {
  if (view === Views.WEEK) {
    const weekStart = moment(date).startOf('week');

    return {
      start: weekStart.toDate(),
      end: weekStart.clone().endOf('week').toDate(),
    };
  }

  if (view === Views.DAY) {
    const dayStart = moment(date).startOf('day');

    return {
      start: dayStart.toDate(),
      end: dayStart.clone().endOf('day').toDate(),
    };
  }

  if (view === Views.AGENDA) {
    const agendaStart = moment(date).startOf('day');

    return {
      start: agendaStart.toDate(),
      end: agendaStart.clone().add(29, 'days').endOf('day').toDate(),
    };
  }

  const monthStart = moment(date).startOf('month').startOf('week');
  const monthEnd = moment(date).endOf('month').endOf('week');

  return {
    start: monthStart.toDate(),
    end: monthEnd.toDate(),
  };
}

export function calculateYearRange(date: Date): CalendarRange {
  const yearStart = moment(date).startOf('year');

  return {
    start: yearStart.toDate(),
    end: yearStart.clone().endOf('year').toDate(),
  };
}

export function normalizeEvents(events: CalendarEventInput[]): CalendarEventRecord[] {
  return events.map((event) => ({
    ...event,
    start: new Date(event.start),
    end: new Date(event.end),
    allDay: Boolean(event.allDay),
    tags: Array.isArray(event.tags) ? event.tags : [],
  }));
}

export function getPreviewEvents(previewEvents?: CalendarEventInput[]): CalendarEventRecord[] | null {
  return Array.isArray(previewEvents) ? normalizeEvents(previewEvents) : null;
}

export function buildRequestUrl(config: CalendarConfig, runtime: CalendarRuntime, activeRange: CalendarRange | null): string {
  const requestUrl = new URL(runtime.restUrl ?? '', globalThis.location?.origin ?? 'http://localhost');

  requestUrl.searchParams.set('per_page', '1000');

  if (Array.isArray(config.postTypes) && config.postTypes.length > 0) {
    requestUrl.searchParams.set('post_types', config.postTypes.join(','));
  }

  if (config.queryVars && Object.keys(config.queryVars).length > 0) {
    requestUrl.searchParams.set('query_vars', JSON.stringify(config.queryVars));
  }

  if (activeRange?.start) {
    requestUrl.searchParams.set('start', activeRange.start.toISOString());
  }

  if (activeRange?.end) {
    requestUrl.searchParams.set('end', activeRange.end.toISOString());
  }

  return requestUrl.toString();
}

function isCalendarRange(range: CalendarRangeInput | null | undefined): range is CalendarRange {
  return Boolean(range) && !Array.isArray(range) && range.start instanceof Date && range.end instanceof Date;
}
