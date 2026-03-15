import { useSyncExternalStore } from 'react';
import { Calendar, Views, momentLocalizer } from 'react-big-calendar';
import moment from 'moment';
import type { CalendarEventRecord, CalendarRange } from '../types.ts';
import { getEventClassName, getRuntimeStrings, useCalendarLocale, YEAR_VIEW, type CalendarView } from './calendar-state.ts';
import CalendarYearView from './calendar-year-view.tsx';
import { CalendarParentStore } from './calendar-parent-store.ts';

const localizer = momentLocalizer(moment);

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

interface CalendarPaneAppProps {
  paneView: CalendarView;
  store: CalendarParentStore;
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

export default function CalendarPaneApp({ paneView, store }: CalendarPaneAppProps) {
  const snapshot = useSyncExternalStore(
    (listener) => store.subscribe(listener),
    () => store.getSnapshot(),
    () => store.getSnapshot(),
  );
  const runtime = store.getRuntime();
  const strings = getRuntimeStrings(runtime);
  const culture = useCalendarLocale(runtime.locale);
  const localizedYearView = Object.assign(
    (props: Parameters<typeof CalendarYearView>[0]) => <CalendarYearView {...props} strings={strings} />,
    {
      navigate: CalendarYearView.navigate,
      range: CalendarYearView.range,
      title: CalendarYearView.title,
    }
  );
  const surfaceClassName = `post-calendar-surface${paneView === Views.AGENDA ? ' is-agenda-view' : ''}${paneView === YEAR_VIEW ? ' is-year-view' : ''}`;
  const calendarViews: Record<string, boolean | typeof CalendarYearView> = {
    [paneView]: paneView === YEAR_VIEW ? localizedYearView : true,
  };

  return (
    <div className="post-calendar-app">
      {snapshot.errorMessage && (
        <p className="post-calendar-error">{snapshot.errorMessage}</p>
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
            week: {
              header: WeekHeader,
            },
          }}
          culture={culture}
          date={snapshot.currentDate}
          events={snapshot.events}
          formats={calendarFormats}
          length={snapshot.agendaLength}
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
            className: getEventClassName(event.start, snapshot.currentDate, paneView),
          })}
          onNavigate={(nextDate) => {
            store.setCurrentDate(nextDate);
          }}
          onSelectEvent={(event) => {
            if (event.url) {
              globalThis.location?.assign(event.url);
            }
          }}
          onYearMonthSelect={(nextDate: Date) => {
            store.setCurrentDate(nextDate);
            store.setActiveView(Views.MONTH);
          }}
          popup
          showMultiDayTimes
          startAccessor="start"
          toolbar={false}
          view={paneView}
          views={calendarViews}
        />
      </div>
    </div>
  );
}