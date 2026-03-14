import { useEffect, useMemo, useState } from 'react';
import { Calendar, Views, momentLocalizer } from 'react-big-calendar';
import moment from 'moment';
import type { CalendarConfig, CalendarEventRecord, CalendarRange, CalendarRuntime, CalendarRuntimeStrings } from '../types.ts';
import {
  getEventClassName,
  getRuntimeStrings,
  normalizeView,
  allowedViews,
  YEAR_VIEW,
  type CalendarRangeInput,
  type CalendarView,
  useCalendarLocale,
  useCalendarState,
} from './calendar-state.ts';
import CalendarYearView from './calendar-year-view.tsx';

const localizer = momentLocalizer(moment);

interface CalendarAppProps {
  config: CalendarConfig;
  hostElement: HTMLElement;
  runtime: CalendarRuntime;
}

interface CalendarEventProps {
  agendaTemplate?: string;
  event: CalendarEventRecord;
}

interface MonthHeaderProps {
  label: string;
}

interface WeekHeaderProps {
  date: Date;
}

interface AgendaDateCellProps {
  day: Date;
  label: string;
}

interface AgendaTimeCellProps {
  event: CalendarEventRecord;
  label: string;
  allDayLabel: string;
}

interface CalendarToolbarProps {
  culture?: string;
  currentDate: Date;
  label: string;
  onNavigate: (action: string) => void;
  onView: (view: CalendarView) => void;
  showToolbarActions: boolean;
  showToolbarLabel: boolean;
  showViewMenu: boolean;
  strings: Required<CalendarRuntimeStrings>;
  view: CalendarView;
  views: readonly CalendarView[];
}

function getToolbarLabel(label: string, view: CalendarView, currentDate: Date, culture?: string): string {
  if (view === Views.DAY) {
    return localizer.format(currentDate, 'MMMM D, YYYY', culture);
  }

  return label;
}

function hasExternalToolbar(hostElement: HTMLElement, config: CalendarConfig): boolean {
  if (config.showToolbar === false) {
    return false;
  }

  if (config.showToolbarActions === false && config.showToolbarLabel === false && config.showViewMenu === false) {
    return false;
  }

  const scope = hostElement.closest('.post-calendar-element') as HTMLElement | null;

  if (!scope) {
    return false;
  }

  return Boolean(scope.querySelector('[data-post-calendar-action], .post-calendar-toolbar-views [role="tab"], [data-post-calendar-label]'));
}

function getExternalViewMenuItems(scope: HTMLElement): HTMLElement[] {
  return Array.from(scope.querySelectorAll<HTMLElement>('.post-calendar-toolbar-views [role="tab"]'));
}

function getExternalViewContentItems(scope: HTMLElement): HTMLElement[] {
  return Array.from(scope.querySelectorAll<HTMLElement>('.post-calendar-view-panels > .post-calendar-content'));
}

function parseOptionalView(value: string | undefined): CalendarView | undefined {
  return allowedViews.includes(value as CalendarView) ? (value as CalendarView) : undefined;
}

function getPairedViewForIndex(scope: HTMLElement, index: number): CalendarView | undefined {
  const contentItems = getExternalViewContentItems(scope);
  const panel = contentItems[index]?.querySelector<HTMLElement>('[data-post-calendar-view-panel]');
  const view = parseOptionalView(panel?.dataset.postCalendarViewPanel);

  return panel ? view : undefined;
}

function getInitialActiveTabIndex(config: CalendarConfig, fallbackIndex: number): number {
  const rawIndex = config.openTab;
  const numericIndex = typeof rawIndex === 'string' ? Number.parseInt(rawIndex, 10) : Number(rawIndex ?? fallbackIndex);

  return Number.isInteger(numericIndex) && numericIndex >= 0 ? numericIndex : fallbackIndex;
}

function syncExternalViewPanels(scope: HTMLElement, activeIndex: number): void {
  const menuItems = getExternalViewMenuItems(scope);
  const contentItems = getExternalViewContentItems(scope);

  contentItems.forEach((contentItem, index) => {
    const isActive = index === activeIndex && (menuItems.length === 0 || index < menuItems.length);

    contentItem.hidden = !isActive;
    contentItem.classList.toggle('is-active', isActive);
    contentItem.setAttribute('aria-hidden', String(!isActive));

    contentItem.querySelectorAll<HTMLElement>('[data-post-calendar-view-panel]').forEach((panel) => {
      panel.hidden = !isActive;
      panel.classList.toggle('is-active', isActive);
      panel.setAttribute('aria-hidden', String(!isActive));
    });
  });
}

function syncExternalToolbarRegions(scope: HTMLElement, config: CalendarConfig): void {
  const visibilityMap: Record<string, boolean> = {
    actions: config.showToolbarActions !== false,
    label: config.showToolbarLabel !== false,
    views: config.showViewMenu !== false,
  };

  scope.querySelectorAll<HTMLElement>('[data-post-calendar-toolbar-region]').forEach((region) => {
    const regionKey = region.dataset.postCalendarToolbarRegion;

    if (!regionKey || !(regionKey in visibilityMap)) {
      return;
    }

    const isVisible = visibilityMap[regionKey];
    region.hidden = !isVisible;
    region.setAttribute('aria-hidden', String(!isVisible));
  });
}

function getExternalToolbarLabel(view: CalendarView, currentDate: Date, culture: string | undefined, agendaLength: number): string {
  switch (view) {
    case Views.DAY:
      return localizer.format(currentDate, 'MMMM D, YYYY', culture);
    case Views.WEEK: {
      const start = moment(currentDate).startOf('week').toDate();
      const end = moment(currentDate).endOf('week').toDate();
      const startLabel = localizer.format(start, 'D MMM', culture);
      const endLabel = localizer.format(end, 'D MMM YYYY', culture);
      return `${startLabel} - ${endLabel}`;
    }
    case Views.AGENDA: {
      const start = moment(currentDate).startOf('day').toDate();
      const end = moment(currentDate).add(Math.max(1, agendaLength) - 1, 'days').endOf('day').toDate();
      const startLabel = localizer.format(start, 'MMMM D', culture);
      const endLabel = localizer.format(end, 'MMMM D, YYYY', culture);
      return `${startLabel} - ${endLabel}`;
    }
    case YEAR_VIEW:
      return localizer.format(currentDate, 'YYYY', culture);
    case Views.MONTH:
    default:
      return localizer.format(currentDate, 'MMMM YYYY', culture);
  }
}

function getDateForToolbarAction(action: string, currentDate: Date, view: CalendarView, agendaLength: number): Date {
  if (action === 'TODAY') {
    return new Date();
  }

  const direction = action === 'PREV' ? -1 : 1;
  const nextDate = moment(currentDate);

  switch (view) {
    case Views.WEEK:
      nextDate.add(direction, 'week');
      break;
    case Views.DAY:
      nextDate.add(direction, 'day');
      break;
    case Views.AGENDA:
      nextDate.add(direction * Math.max(1, agendaLength), 'days');
      break;
    case YEAR_VIEW:
      nextDate.add(direction, 'year');
      break;
    case Views.MONTH:
    default:
      nextDate.add(direction, 'month');
      break;
  }

  return nextDate.toDate();
}

function getViewLabels(strings: Required<CalendarRuntimeStrings>): Record<CalendarView, string> {
  return {
    [Views.MONTH]: strings.month,
    [Views.WEEK]: strings.week,
    [Views.DAY]: strings.day,
    [Views.AGENDA]: strings.agenda,
    [YEAR_VIEW]: strings.year,
  };
}

function CalendarEvent({ event }: CalendarEventProps) {
  return (
    <span className="post-calendar-event-pill">
      {event.url ? (
        <a href={event.url} className="post-calendar-event-link">
          {event.title}
        </a>
      ) : (
        <span>{event.title}</span>
      )}
    </span>
  );
}

function MonthHeader({ label }: MonthHeaderProps) {
  return <span className="post-calendar-column-label">{label}</span>;
}

function WeekHeader({ date }: WeekHeaderProps) {
  return (
    <div className="post-calendar-week-header">
      <span className="post-calendar-column-label">{moment(date).format('ddd')}</span>
      <span className="post-calendar-column-date">{moment(date).format('DD')}</span>
    </div>
  );
}

function TimeGutterHeader({ label }: { label: string }) {
  return <span className="post-calendar-time-gutter-label">{label}</span>;
}

function AgendaDateCell({ day, label }: AgendaDateCellProps) {
  return (
    <div className="post-calendar-agenda-date">
      <span className="post-calendar-agenda-weekday">{moment(day).format('dddd')}</span>
      <span className="post-calendar-agenda-full-date">{label}</span>
    </div>
  );
}

function AgendaTimeCell({ event, label, allDayLabel }: AgendaTimeCellProps) {
  return <span className="post-calendar-agenda-time">{event.allDay ? allDayLabel : label}</span>;
}

function getAgendaFieldValue(fieldName: string, event: CalendarEventRecord): string {
  switch (fieldName) {
    case 'title':
      return event.title;
    case 'excerpt':
      return event.excerpt ?? '';
    case 'tags':
      return event.tags.join(', ');
    default:
      return '';
  }
}

function getAgendaLinkValue(fieldName: string, event: CalendarEventRecord): string {
  switch (fieldName) {
    case 'url':
      return event.url ?? '';
    default:
      return '';
  }
}

function renderAgendaTemplate(agendaTemplate: string | undefined, event: CalendarEventRecord): string | null {
  if (!agendaTemplate || typeof document === 'undefined') {
    return null;
  }

  const template = document.createElement('template');
  template.innerHTML = agendaTemplate.trim();

  if (!template.content.childNodes.length) {
    return null;
  }

  const fragment = template.content.cloneNode(true) as DocumentFragment;
  const boundNodes = fragment.querySelectorAll<HTMLElement>('[data-post-calendar-field], [data-post-calendar-link-field]');

  boundNodes.forEach((node) => {
    const fieldName = node.dataset.postCalendarField;
    const linkFieldName = node.dataset.postCalendarLinkField;
    const fieldValue = fieldName ? getAgendaFieldValue(fieldName, event) : '';
    const linkValue = linkFieldName ? getAgendaLinkValue(linkFieldName, event) : '';
    const shouldHide = node.dataset.postCalendarHideEmpty === 'true' && !fieldValue && !linkValue;

    if (shouldHide) {
      node.remove();
      return;
    }

    if (fieldName) {
      node.textContent = fieldValue;
    }

    if (linkFieldName && node instanceof HTMLAnchorElement) {
      if (linkValue) {
        node.href = linkValue;
      } else {
        node.removeAttribute('href');
      }
    }
  });

  const container = document.createElement('div');
  container.appendChild(fragment);

  return container.innerHTML.trim() || null;
}

function AgendaEventCell({ agendaTemplate, event }: CalendarEventProps) {
  const renderedTemplate = renderAgendaTemplate(agendaTemplate, event);

  if (renderedTemplate) {
    return <div className="post-calendar-agenda-template-render" dangerouslySetInnerHTML={{ __html: renderedTemplate }} />;
  }

  return (
    <div className="post-calendar-agenda-event">
      <div className="post-calendar-agenda-copy">
        {event.url ? (
          <a href={event.url} className="post-calendar-agenda-title">
            {event.title}
          </a>
        ) : (
          <span className="post-calendar-agenda-title">{event.title}</span>
        )}

        {event.excerpt ? <p className="post-calendar-agenda-excerpt">{event.excerpt}</p> : null}
      </div>

      {event.tags.length > 0 ? <span className="post-calendar-agenda-tag-list">{event.tags.join(', ')}</span> : null}
    </div>
  );
}

function CalendarToolbar({ culture, currentDate, label, onNavigate, onView, showToolbarActions, showToolbarLabel, showViewMenu, strings, view, views }: CalendarToolbarProps) {
  const viewLabels = getViewLabels(strings);
  const toolbarLabel = getToolbarLabel(label, view, currentDate, culture);

  if (!showToolbarActions && !showToolbarLabel && !showViewMenu) {
    return null;
  }

  return (
    <div className="post-calendar-toolbar">
    {showToolbarActions ? (
      <div className="post-calendar-toolbar-actions">
      <button type="button" className="post-calendar-toolbar-button" onClick={() => onNavigate('TODAY')}>
        {strings.today}
      </button>
      <button type="button" className="post-calendar-toolbar-button" onClick={() => onNavigate('PREV')}>
        {strings.back}
      </button>
      <button type="button" className="post-calendar-toolbar-button" onClick={() => onNavigate('NEXT')}>
        {strings.next}
      </button>
      </div>
    ) : null}

    {showToolbarLabel ? <div className="post-calendar-toolbar-label">{toolbarLabel}</div> : null}

    {showViewMenu ? (
      <div className="post-calendar-toolbar-views" role="tablist" aria-label={strings.calendarViews}>
      {views.map((calendarView) => (
        <button
        key={calendarView}
        type="button"
        className={`post-calendar-view-button${view === calendarView ? ' is-active' : ''}`}
        onClick={() => onView(calendarView)}
        >
        {viewLabels[calendarView]}
        </button>
      ))}
      </div>
    ) : null}
    </div>
  );
}

const calendarFormats = {
  dayFormat: (date: Date, culture: string | undefined, nextLocalizer: typeof localizer) => nextLocalizer.format(date, 'ddd', culture),
  dayHeaderFormat: (date: Date, culture: string | undefined, nextLocalizer: typeof localizer) => nextLocalizer.format(date, 'ddd', culture),
  dayRangeHeaderFormat: ({ start, end }: CalendarRange, culture: string | undefined, nextLocalizer: typeof localizer) => {
    if (moment(start).isSame(end, 'day')) {
      return nextLocalizer.format(start, 'dddd, D MMM YYYY', culture);
    }

    const startLabel = nextLocalizer.format(start, 'D MMM', culture);
    const endLabel = nextLocalizer.format(end, 'D MMM YYYY', culture);
    return `${startLabel} - ${endLabel}`;
  },
  agendaDateFormat: (date: Date, culture: string | undefined, nextLocalizer: typeof localizer) => nextLocalizer.format(date, 'MMMM D, YYYY', culture),
  agendaTimeFormat: (date: Date, culture: string | undefined, nextLocalizer: typeof localizer) => nextLocalizer.format(date, 'h:mm A', culture),
  agendaHeaderFormat: ({ start, end }: CalendarRange, culture: string | undefined, nextLocalizer: typeof localizer) => {
    const startLabel = nextLocalizer.format(start, 'MMMM D', culture);
    const endLabel = nextLocalizer.format(end, 'MMMM D, YYYY', culture);
    return `${startLabel} - ${endLabel}`;
  },
  timeGutterFormat: (date: Date, culture: string | undefined, nextLocalizer: typeof localizer) => nextLocalizer.format(date, 'hh:mm A', culture),
};

export default function CalendarApp({ config, hostElement, runtime }: CalendarAppProps) {
  const strings = getRuntimeStrings(runtime);
  const culture = useCalendarLocale(runtime.locale);
  const externalToolbar = hasExternalToolbar(hostElement, config);
  const {
    activeViews,
    agendaLength,
    currentDate,
    errorMessage,
    events,
    handleRangeChange,
    setCurrentDate,
    setView,
    surfaceClassName,
    view,
  } = useCalendarState(config, runtime, strings);
  const localizedYearView = Object.assign(
    (props: Parameters<typeof CalendarYearView>[0]) => <CalendarYearView {...props} strings={strings} />,
    {
      navigate: CalendarYearView.navigate,
      range: CalendarYearView.range,
      title: CalendarYearView.title,
    }
  );
  const calendarViews = activeViews.reduce<Record<string, boolean | typeof CalendarYearView>>((viewMap, activeView) => {
    viewMap[activeView] = activeView === YEAR_VIEW ? localizedYearView : true;
    return viewMap;
  }, {});
  const externalToolbarLabel = useMemo(
    () => getExternalToolbarLabel(view, currentDate, culture, agendaLength),
    [agendaLength, culture, currentDate, view]
  );
  const [activeTabIndex, setActiveTabIndex] = useState<number>(() => getInitialActiveTabIndex(config, 0));

  useEffect(() => {
    if (!externalToolbar) {
      return undefined;
    }

    const scope = hostElement.closest('.post-calendar-element') as HTMLElement | null;

    if (!scope) {
      return undefined;
    }

    const handleToolbarTarget = (target: HTMLElement, event: Event) => {
      const action = target.dataset.postCalendarAction;

      if (action) {
        event.preventDefault();
        setCurrentDate(getDateForToolbarAction(action.toUpperCase(), currentDate, view, agendaLength));
        return;
      }

      const menuItems = getExternalViewMenuItems(scope);
      const targetIndex = menuItems.indexOf(target);
      const nextView = targetIndex !== -1 ? getPairedViewForIndex(scope, targetIndex) : undefined;

      if (targetIndex !== -1) {
        event.preventDefault();
        setActiveTabIndex(targetIndex);
      }

      if (nextView) {
        setView(nextView);
      }
    };

    const handleClick = (event: Event) => {
      const target = event.target instanceof Element ? event.target.closest<HTMLElement>('[data-post-calendar-action], .post-calendar-toolbar-views [role="tab"]') : null;

      if (!target || !scope.contains(target)) {
        return;
      }

      handleToolbarTarget(target, event);
    };

    const handleKeyDown = (event: KeyboardEvent) => {
      if (event.key !== 'Enter' && event.key !== ' ') {
        return;
      }

      const target = event.target instanceof Element ? event.target.closest<HTMLElement>('[data-post-calendar-action], .post-calendar-toolbar-views [role="tab"]') : null;

      if (!target || !scope.contains(target)) {
        return;
      }

      handleToolbarTarget(target, event);
    };

    scope.addEventListener('click', handleClick);
    scope.addEventListener('keydown', handleKeyDown);

    return () => {
      scope.removeEventListener('click', handleClick);
      scope.removeEventListener('keydown', handleKeyDown);
    };
  }, [agendaLength, currentDate, externalToolbar, hostElement, setActiveTabIndex, setCurrentDate, setView, view]);

  useEffect(() => {
    if (!externalToolbar) {
      return;
    }

    const scope = hostElement.closest('.post-calendar-element') as HTMLElement | null;

    if (!scope) {
      return;
    }

    scope.dataset.activeView = view;

		syncExternalToolbarRegions(scope, config);

    scope.querySelectorAll<HTMLElement>('[data-post-calendar-label]').forEach((labelNode) => {
      labelNode.textContent = externalToolbarLabel;
    });

    getExternalViewMenuItems(scope).forEach((viewNode, index) => {
      const isActive = index === activeTabIndex;

      viewNode.classList.toggle('is-active', isActive);
      viewNode.setAttribute('aria-selected', String(isActive));
      viewNode.setAttribute('tabindex', isActive ? '0' : '-1');
    });

    syncExternalViewPanels(scope, activeTabIndex);
  }, [activeTabIndex, config, externalToolbar, externalToolbarLabel, hostElement]);

  return (
    <div className="post-calendar-app">
      {errorMessage && (
        <p className="post-calendar-error">{errorMessage}</p>
      )}

      <div className={surfaceClassName}>
        <Calendar
          components={{
            agenda: {
              date: AgendaDateCell,
              event: (props) => <AgendaEventCell {...props} agendaTemplate={config.agendaTemplate} />,
              time: (props) => <AgendaTimeCell {...props} allDayLabel={strings.allDay} />,
            },
            event: CalendarEvent,
            month: {
              header: MonthHeader,
            },
            timeGutterHeader: () => <TimeGutterHeader label={strings.allDay} />,
            toolbar: externalToolbar
              ? undefined
              : (props) => (
                  <CalendarToolbar
                    {...props}
                    culture={culture}
                    currentDate={currentDate}
                    strings={strings}
                    showToolbarActions={config.showToolbarActions !== false}
                    showToolbarLabel={config.showToolbarLabel !== false}
                    showViewMenu={config.showViewMenu !== false}
                    views={activeViews}
                  />
                ),
            week: {
              header: WeekHeader,
            },
          }}
          culture={culture}
          date={currentDate}
          events={events}
          formats={calendarFormats}
          length={agendaLength}
          localizer={localizer}
          messages={{
            agenda: strings.agenda,
            date: strings.date,
            day: strings.day,
            event: strings.event,
            month: strings.month,
            next: strings.next,
            noEventsInRange: strings.noEvents,
            previous: strings.back,
            showMore: (count) => `+${count} ${strings.showMore}`,
            time: strings.time,
            today: strings.today,
            week: strings.week,
          }}
          eventPropGetter={(event) => ({
            className: getEventClassName(event.start, currentDate, view),
          })}
          onNavigate={(nextDate) => {
            setCurrentDate(nextDate);
          }}
          onRangeChange={(nextRange) => {
            handleRangeChange(nextRange as CalendarRangeInput);
          }}
          onSelectEvent={(event) => {
            if (event.url) {
              globalThis.location?.assign(event.url);
            }
          }}
          onYearMonthSelect={(nextDate: Date) => {
            setCurrentDate(nextDate);
            setView(Views.MONTH);
          }}
          onView={(nextView) => {
            setView(normalizeView(nextView));
          }}
          popup
          showMultiDayTimes
          startAccessor="start"
          toolbar={config.showToolbar !== false && !externalToolbar}
          view={view}
          views={calendarViews}
        />
      </div>

    </div>
  );
}