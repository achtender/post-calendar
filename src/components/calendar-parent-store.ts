import moment from 'moment';
import { Views } from 'react-big-calendar';
import type { CalendarConfig, CalendarRuntime, CalendarSharedSnapshot, CalendarRange } from '../types.ts';
import {
  agendaRangeModes,
  allowedViews,
  buildRequestUrl,
  calculateAgendaWindow,
  calculateFallbackRange,
  calculateYearRange,
  getPreviewEvents,
  getRuntimeStrings,
  normalizeEvents,
  YEAR_VIEW,
  type CalendarView,
} from './calendar-state.ts';

type Listener = () => void;

interface BricksTabsChangedDetail {
  activeIndex?: number;
  activePane?: Element | null;
}

function normalizeOptionalView(value: string | undefined): CalendarView | undefined {
  return allowedViews.includes(value as CalendarView) ? (value as CalendarView) : undefined;
}

function normalizePositiveInteger(value: number | string | undefined, fallback: number): number {
  const parsedValue = Number.parseInt(String(value), 10);

  return Number.isFinite(parsedValue) && parsedValue > 0 ? parsedValue : fallback;
}

function normalizeAgendaRangeMode(mode?: string): string {
  return mode === agendaRangeModes.UPCOMING_WINDOW ? agendaRangeModes.UPCOMING_WINDOW : agendaRangeModes.VISIBLE_RANGE;
}

function getOrderedViewPanels(scope: HTMLElement): HTMLElement[] {
  return getOrderedContentItems(scope)
    .map((contentItem) => contentItem.querySelector<HTMLElement>('[data-post-calendar-view-panel]'))
    .filter((panel): panel is HTMLElement => Boolean(panel));
}

function getOrderedContentItems(scope: HTMLElement): HTMLElement[] {
  const contentRoot = scope.querySelector<HTMLElement>('.tab-content');

  if (!contentRoot) {
    return [];
  }

  return Array.from(contentRoot.children).filter(
    (element): element is HTMLElement => element instanceof HTMLElement && element.classList.contains('tab-pane')
  );
}

function getClampedPaneIndex(scope: HTMLElement, value: number): number {
  const contentItems = getOrderedContentItems(scope);

  if (contentItems.length === 0) {
    return 0;
  }

  return Math.min(Math.max(0, value), contentItems.length - 1);
}

function getPaneViews(scope: HTMLElement): CalendarView[] {
  const orderedViews = getOrderedViewPanels(scope)
    .map((panel) => normalizeOptionalView(panel.dataset.postCalendarViewPanel))
    .filter((view): view is CalendarView => Boolean(view));

  return Array.from(new Set(orderedViews));
}

function getContentView(scope: HTMLElement, contentItem: HTMLElement): CalendarView | undefined {
  const panel = contentItem.querySelector<HTMLElement>('[data-post-calendar-view-panel]');

  return normalizeOptionalView(panel?.dataset.postCalendarViewPanel);
}

function getInitialActiveView(scope: HTMLElement, config: CalendarConfig, activeViews: CalendarView[]): CalendarView {
  const defaultView = normalizeOptionalView(config.defaultView);

  if (defaultView && activeViews.includes(defaultView)) {
    return defaultView;
  }

  const openTabIndex = typeof config.openTab === 'string' ? Number.parseInt(config.openTab, 10) : Number(config.openTab ?? 0);

  if (Number.isInteger(openTabIndex) && openTabIndex >= 0) {
    const contentItem = getOrderedContentItems(scope)[openTabIndex];
    const contentView = contentItem ? getContentView(scope, contentItem) : undefined;

    if (contentView && activeViews.includes(contentView)) {
      return contentView;
    }
  }

  return activeViews[0] ?? Views.MONTH;
}

function getInitialActivePaneIndex(scope: HTMLElement, config: CalendarConfig): number {
  const openTabIndex = typeof config.openTab === 'string' ? Number.parseInt(config.openTab, 10) : Number(config.openTab ?? 0);

  return getClampedPaneIndex(scope, Number.isInteger(openTabIndex) && openTabIndex >= 0 ? openTabIndex : 0);
}

function mergeRanges(ranges: CalendarRange[]): CalendarRange | null {
  if (ranges.length === 0) {
    return null;
  }

  return ranges.reduce<CalendarRange>((combined, nextRange) => ({
    start: combined.start <= nextRange.start ? combined.start : nextRange.start,
    end: combined.end >= nextRange.end ? combined.end : nextRange.end,
  }));
}

function getSharedFetchRange(config: CalendarConfig, currentDate: Date, activeViews: CalendarView[]): CalendarRange | null {
  const agendaRangeMode = normalizeAgendaRangeMode(config.agendaRangeMode);
  const agendaRangeMonths = normalizePositiveInteger(config.agendaRangeMonths, 3);
  const ranges = activeViews.map((view) => {
    if (view === Views.AGENDA && agendaRangeMode === agendaRangeModes.UPCOMING_WINDOW) {
      const agendaWindow = calculateAgendaWindow(currentDate, agendaRangeMonths);

      return {
        start: agendaWindow.start,
        end: agendaWindow.end,
      };
    }

    if (view === YEAR_VIEW) {
      return calculateYearRange(currentDate);
    }

    return calculateFallbackRange(view, currentDate);
  });

  return mergeRanges(ranges);
}

function getToolbarLabel(view: CalendarView, currentDate: Date, agendaLength: number, culture: string | undefined): string {
  switch (view) {
    case Views.DAY:
      return moment(currentDate).locale(culture ?? moment.locale()).format('MMMM D, YYYY');
    case Views.WEEK: {
      const start = moment(currentDate).startOf('week');
      const end = moment(currentDate).endOf('week');
      return `${start.locale(culture ?? moment.locale()).format('D MMM')} - ${end.locale(culture ?? moment.locale()).format('D MMM YYYY')}`;
    }
    case Views.AGENDA: {
      const start = moment(currentDate).startOf('day');
      const end = start.clone().add(Math.max(1, agendaLength) - 1, 'days').endOf('day');
      return `${start.locale(culture ?? moment.locale()).format('MMMM D')} - ${end.locale(culture ?? moment.locale()).format('MMMM D, YYYY')}`;
    }
    case YEAR_VIEW:
      return moment(currentDate).locale(culture ?? moment.locale()).format('YYYY');
    case Views.MONTH:
    default:
      return moment(currentDate).locale(culture ?? moment.locale()).format('MMMM YYYY');
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

export class CalendarParentStore {
  private readonly config: CalendarConfig;
  private readonly runtime: CalendarRuntime;
  private readonly scope: HTMLElement;
  private readonly listeners = new Set<Listener>();
  private readonly boundClick: (event: Event) => void;
  private readonly boundTabsChanged: (event: Event) => void;
  private abortController: AbortController | null = null;
  private snapshot: CalendarSharedSnapshot;

  constructor(scope: HTMLElement, config: CalendarConfig, runtime: CalendarRuntime) {
    const paneViews = getPaneViews(scope);
    const activeViews = paneViews.length > 0 ? paneViews : allowedViews.slice();
    const initialActivePaneIndex = getInitialActivePaneIndex(scope, config);
    const initialActiveView = getInitialActiveView(scope, config, activeViews);
    const agendaLength = normalizeAgendaRangeMode(config.agendaRangeMode) === agendaRangeModes.UPCOMING_WINDOW
      ? calculateAgendaWindow(new Date(), normalizePositiveInteger(config.agendaRangeMonths, 3)).length
      : 30;

    this.scope = scope;
    this.config = {
      ...config,
      enabledViews: activeViews,
      ...(initialActiveView ? { defaultView: initialActiveView } : {}),
    };
    this.runtime = runtime;
    this.snapshot = {
      activePaneIndex: initialActivePaneIndex,
      activeView: initialActiveView,
      activeViews: activeViews.slice(),
      agendaLength,
      currentDate: new Date(),
      errorMessage: config.error ?? '',
      events: [],
      isLoading: false,
    };

    this.boundClick = (event) => {
      const target = event.target instanceof Element ? event.target.closest<HTMLElement>('[data-post-calendar-action]') : null;

      if (!target || !this.scope.contains(target)) {
        return;
      }

      const action = target.dataset.postCalendarAction;

      if (action) {
        event.preventDefault();
        this.setCurrentDate(getDateForToolbarAction(action.toUpperCase(), this.snapshot.currentDate, this.getActiveView(), this.snapshot.agendaLength));
      }
    };

    this.boundTabsChanged = (event) => {
      const detail = event instanceof CustomEvent ? event.detail as BricksTabsChangedDetail : null;

      if (!detail?.activePane || !this.scope.contains(detail.activePane)) {
        return;
      }

      this.syncActivePaneFromDom();
    };

    this.scope.addEventListener('click', this.boundClick);
    document.addEventListener('bricks/tabs/changed', this.boundTabsChanged as EventListener);

    this.syncActivePaneFromDom(false);
    this.syncDom();
    void this.loadEvents();
  }

  subscribe(listener: Listener): () => void {
    this.listeners.add(listener);

    return () => {
      this.listeners.delete(listener);
    };
  }

  getSnapshot(): CalendarSharedSnapshot {
    return this.snapshot;
  }

  getConfig(): CalendarConfig {
    return this.config;
  }

  getRuntime(): CalendarRuntime {
    return this.runtime;
  }

  getActiveView(): CalendarView {
    return normalizeOptionalView(this.snapshot.activeView) ?? Views.MONTH;
  }

  setActivePaneIndex(index: number): void {
    const nextIndex = getClampedPaneIndex(this.scope, index);

    if (this.activatePaneIndex(nextIndex)) {
      return;
    }

    const nextView = this.getViewForPaneIndex(nextIndex) ?? this.getActiveView();

    this.snapshot = {
      ...this.snapshot,
      activePaneIndex: nextIndex,
      activeView: nextView,
    };

    this.syncDom();
    this.emitChange();
  }

  setActiveView(view: CalendarView): void {
    if (!this.snapshot.activeViews.includes(view)) {
      return;
    }

    const paneIndex = this.getPaneIndexForView(view);

    if (paneIndex !== -1 && this.activatePaneIndex(paneIndex)) {
      return;
    }

    this.snapshot = {
      ...this.snapshot,
      ...(paneIndex !== -1 ? { activePaneIndex: paneIndex } : {}),
      activeView: view,
    };

    this.syncDom();
    this.emitChange();
  }

  setCurrentDate(date: Date): void {
    this.snapshot = {
      ...this.snapshot,
      currentDate: date,
      agendaLength: normalizeAgendaRangeMode(this.config.agendaRangeMode) === agendaRangeModes.UPCOMING_WINDOW
        ? calculateAgendaWindow(date, normalizePositiveInteger(this.config.agendaRangeMonths, 3)).length
        : 30,
    };

    this.syncDom();
    this.emitChange();
    void this.loadEvents();
  }

  destroy(): void {
    this.abortController?.abort();
    this.scope.removeEventListener('click', this.boundClick);
    document.removeEventListener('bricks/tabs/changed', this.boundTabsChanged as EventListener);
    this.listeners.clear();
  }

  private emitChange(): void {
    this.listeners.forEach((listener) => {
      listener();
    });
  }

  private getMenuItems(): HTMLElement[] {
    const menu = this.scope.querySelector<HTMLElement>('.tab-menu');

    if (!menu) {
      return [];
    }

    return Array.from(menu.children).filter(
      (element): element is HTMLElement => element instanceof HTMLElement && element.classList.contains('tab-title')
    );
  }

  private getViewForPaneIndex(index: number): CalendarView | undefined {
    const contentItem = getOrderedContentItems(this.scope)[index];

    return contentItem ? getContentView(this.scope, contentItem) : undefined;
  }

  private getPaneIndexForView(view: CalendarView): number {
    return getOrderedContentItems(this.scope).findIndex((contentItem) => getContentView(this.scope, contentItem) === view);
  }

  private getActivePaneView(): CalendarView | undefined {
    return this.getViewForPaneIndex(this.snapshot.activePaneIndex);
  }

  private getCurrentOpenPaneIndex(): number {
    const contentItems = getOrderedContentItems(this.scope);
    const openPaneIndex = contentItems.findIndex((contentItem) => contentItem.classList.contains('brx-open'));

    if (openPaneIndex !== -1) {
      return openPaneIndex;
    }

    return getClampedPaneIndex(this.scope, this.snapshot.activePaneIndex);
  }

  private activatePaneIndex(index: number): boolean {
    const menuItems = this.getMenuItems();
    const target = menuItems[index];

    if (!target) {
      return false;
    }

    if (target.classList.contains('brx-open')) {
      this.syncActivePaneFromDom();
      return true;
    }

    target.click();
    return true;
  }

  private syncActivePaneFromDom(shouldEmit = true): void {
    const nextIndex = this.getCurrentOpenPaneIndex();
    const nextView = this.getViewForPaneIndex(nextIndex) ?? this.snapshot.activeView;
    const hasChanged = nextIndex !== this.snapshot.activePaneIndex || nextView !== this.snapshot.activeView;

    if (!hasChanged) {
      return;
    }

    this.snapshot = {
      ...this.snapshot,
      activePaneIndex: nextIndex,
      activeView: nextView,
    };

    this.syncDom();

    if (shouldEmit) {
      this.emitChange();
    }
  }

  private syncToolbarRegions(): void {
    const toolbarVisible = this.config.showToolbar !== false;
    const activePaneView = this.getActivePaneView();
    const hasActivePaneView = Boolean(activePaneView);
    const visibilityMap: Record<string, boolean> = {
      actions: toolbarVisible && this.config.showToolbarActions !== false && hasActivePaneView,
      label: toolbarVisible && this.config.showToolbarLabel !== false && hasActivePaneView,
      views: toolbarVisible && this.config.showViewMenu !== false,
    };

    this.scope.querySelectorAll<HTMLElement>('[data-post-calendar-toolbar-region]').forEach((region) => {
      const regionKey = region.dataset.postCalendarToolbarRegion;

      if (!regionKey || !(regionKey in visibilityMap)) {
        return;
      }

      const isVisible = visibilityMap[regionKey];
      region.hidden = !isVisible;
      region.setAttribute('aria-hidden', String(!isVisible));
    });
  }

  private syncDom(): void {
    const activeView = this.getActiveView();
    const activePaneView = this.getActivePaneView();
    const culture = this.runtime.locale?.replace(/_/g, '-');
    const toolbarLabel = activePaneView ? getToolbarLabel(activeView, this.snapshot.currentDate, this.snapshot.agendaLength, culture) : '';

    this.scope.dataset.activeView = activePaneView ?? '';

    this.syncToolbarRegions();

    this.scope.querySelectorAll<HTMLElement>('[data-post-calendar-label]').forEach((labelNode) => {
      labelNode.textContent = toolbarLabel;
    });
  }

  private async loadEvents(): Promise<void> {
    const previewEvents = getPreviewEvents(this.runtime.previewEvents);

    if (previewEvents) {
      this.snapshot = {
        ...this.snapshot,
        events: previewEvents,
        errorMessage: this.config.error ?? '',
        isLoading: false,
      };
      this.emitChange();
      return;
    }

    if (!this.runtime.restUrl) {
      this.snapshot = {
        ...this.snapshot,
        errorMessage: getRuntimeStrings(this.runtime).missingApiUrl,
        isLoading: false,
      };
      this.emitChange();
      return;
    }

    this.abortController?.abort();
    this.abortController = new AbortController();

    this.snapshot = {
      ...this.snapshot,
      isLoading: true,
    };
    this.emitChange();

    const activeRange = getSharedFetchRange(this.config, this.snapshot.currentDate, this.snapshot.activeViews.map((view) => normalizeOptionalView(view)).filter((view): view is CalendarView => Boolean(view)));
    const requestUrl = buildRequestUrl(this.config, this.runtime, activeRange);

    try {
      const response = await fetch(requestUrl, {
        headers: {
          'X-WP-Nonce': this.runtime.restNonce ?? '',
        },
        signal: this.abortController.signal,
      });

      if (!response.ok) {
        throw new Error(`Request failed with status ${response.status}`);
      }

      const payload = await response.json();

      this.snapshot = {
        ...this.snapshot,
        events: normalizeEvents(Array.isArray(payload) ? payload : []),
        errorMessage: '',
        isLoading: false,
      };
    } catch (error) {
      if (error instanceof DOMException && error.name === 'AbortError') {
        return;
      }

      this.snapshot = {
        ...this.snapshot,
        errorMessage: getRuntimeStrings(this.runtime).loadError,
        isLoading: false,
      };
    }

    this.emitChange();
  }
}