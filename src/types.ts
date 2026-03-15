export interface CalendarConfig {
  defaultView?: string;
  enabledViews?: string[];
  openTab?: number | string;
  showToolbar?: boolean;
  showToolbarActions?: boolean;
  showToolbarLabel?: boolean;
  showViewMenu?: boolean;
  agendaRangeMode?: string;
  agendaRangeMonths?: number | string;
  queryVars?: Record<string, unknown>;
  postTypes?: string[];
  error?: string;
}

export interface CalendarSharedSnapshot {
	activePaneIndex: number;
  activeView: string;
  activeViews: string[];
  agendaLength: number;
  currentDate: Date;
  errorMessage: string;
  events: CalendarEventRecord[];
  isLoading: boolean;
}

export interface CalendarEventInput {
  id?: number | string;
  title: string;
  start: string | Date;
  end: string | Date;
  allDay?: boolean;
  url?: string;
  excerpt?: string;
  tags?: string[];
}

export interface CalendarEventRecord extends Omit<CalendarEventInput, 'start' | 'end'> {
  start: Date;
  end: Date;
  allDay: boolean;
  tags: string[];
}

export interface CalendarRuntime {
  restUrl?: string;
  restNonce?: string;
  locale?: string;
  previewEvents?: CalendarEventInput[];
  strings?: CalendarRuntimeStrings;
}

export interface CalendarRuntimeStrings {
  allDay?: string;
  agenda?: string;
  back?: string;
  calendarViews?: string;
  configParseError?: string;
  date?: string;
  day?: string;
  event?: string;
  loadError?: string;
  missingApiUrl?: string;
  month?: string;
  next?: string;
  noEvents?: string;
  showMore?: string;
  showMoreEventsForMonth?: string;
  time?: string;
  today?: string;
  week?: string;
  year?: string;
}

export interface CalendarRange {
  start: Date;
  end: Date;
}

export type EventRepeatValue = 'none' | 'weekly' | 'monthly' | 'yearly';

export interface AdminEventRow {
  label: string;
  all_day: boolean;
  start_date: string;
  end_date: string;
  repeat: EventRepeatValue;
  repeat_interval: number;
  repeat_byday: string[];
  repeat_until: string;
}

export interface AdminRuntimeStrings {
  addEvent?: string;
  allDay?: string;
  doesNotRepeat?: string;
  endDate?: string;
  eventLabel?: string;
  eventLabelHelp?: string;
  eventNumber?: string;
  eventRepeat?: string;
  eventsIntro?: string;
  friday?: string;
  monday?: string;
  monthly?: string;
  noEvents?: string;
  removeEvent?: string;
  repeatInterval?: string;
  repeatIntervalHelp?: string;
  repeatOn?: string;
  repeatUntil?: string;
  saturday?: string;
  startDate?: string;
  sunday?: string;
  thursday?: string;
  tuesday?: string;
  wednesday?: string;
  weekly?: string;
  yearly?: string;
}

export interface AdminRuntime {
  currentEvents?: AdminEventRow[];
  fieldName?: string;
  strings?: AdminRuntimeStrings;
}