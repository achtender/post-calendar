import { Calendar, Views, momentLocalizer } from 'react-big-calendar';
import moment from 'moment';
import type { CalendarConfig, CalendarEventRecord, CalendarRange, CalendarRuntime, CalendarRuntimeStrings } from '../types.ts';
import {
  allowedViews,
  getEventClassName,
  getRuntimeStrings,
  normalizeView,
  type CalendarRangeInput,
  type CalendarView,
  useCalendarLocale,
  useCalendarState,
} from './calendar-state.ts';

const localizer = momentLocalizer(moment);

interface CalendarAppProps {
  config: CalendarConfig;
  runtime: CalendarRuntime;
}

interface CalendarEventProps {
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

function getViewLabels(strings: Required<CalendarRuntimeStrings>): Record<CalendarView, string> {
  return {
    [Views.MONTH]: strings.month,
    [Views.WEEK]: strings.week,
    [Views.DAY]: strings.day,
    [Views.AGENDA]: strings.agenda,
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

function AgendaEventCell({ event }: CalendarEventProps) {
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

function CalendarToolbar({ culture, currentDate, label, onNavigate, onView, strings, view, views }: CalendarToolbarProps) {
  const viewLabels = getViewLabels(strings);
  const toolbarLabel = getToolbarLabel(label, view, currentDate, culture);

  return (
    <div className="post-calendar-toolbar">
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

      <div className="post-calendar-toolbar-label">{toolbarLabel}</div>

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

export default function CalendarApp({ config, runtime }: CalendarAppProps) {
  const strings = getRuntimeStrings(runtime);
  const culture = useCalendarLocale(runtime.locale);
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
              event: AgendaEventCell,
              time: (props) => <AgendaTimeCell {...props} allDayLabel={strings.allDay} />,
            },
            event: CalendarEvent,
            month: {
              header: MonthHeader,
            },
            timeGutterHeader: () => <TimeGutterHeader label={strings.allDay} />,
            toolbar: (props) => (
              <CalendarToolbar
                {...props}
                culture={culture}
                currentDate={currentDate}
                strings={strings}
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
            noEventsInRange: config.emptyMessage || strings.noEvents,
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
          onView={(nextView) => {
            setView(normalizeView(nextView));
          }}
          popup
          showMultiDayTimes
          startAccessor="start"
          toolbar={config.showToolbar !== false}
          view={view}
          views={activeViews as unknown as CalendarView[]}
        />
      </div>

    </div>
  );
}