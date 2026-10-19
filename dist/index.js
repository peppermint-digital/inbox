// resources/js/src/InboxPage.tsx
import { useMemo, useState } from "react";

// resources/js/src/ui/utils.ts
import { clsx } from "clsx";
import { twMerge } from "tailwind-merge";
function cn(...inputs) {
  return twMerge(clsx(inputs));
}

// resources/js/src/ui/Badge.tsx
import { jsx } from "react/jsx-runtime";
function Badge({ variant = "secondary", className, ...props }) {
  return /* @__PURE__ */ jsx(
    "span",
    {
      className: cn(
        "inline-flex items-center rounded-full px-1.5 py-0.5 text-[0.625rem] leading-none font-medium",
        variant === "secondary" ? "bg-secondary text-secondary-foreground" : "border border-border text-muted-foreground",
        className
      ),
      ...props
    }
  );
}

// resources/js/src/icons.ts
import {
  AlarmClock,
  AlertTriangle,
  AtSign,
  Bell,
  BookOpen,
  Calendar,
  CalendarCheck,
  CalendarClock,
  CalendarPlus,
  CalendarX,
  CheckCircle,
  Clock,
  FileText,
  FolderCog,
  FolderMinus,
  FolderPlus,
  FolderX,
  Globe,
  Inbox,
  Lightbulb,
  Lock,
  Mail,
  MailWarning,
  Megaphone,
  MessageCircle,
  PackageCheck,
  PhoneMissed,
  RefreshCw,
  Server,
  ShieldAlert,
  Thermometer,
  Trash2,
  Unlock,
  UserMinus,
  UserPlus,
  XCircle
} from "lucide-react";
var ICONS = {
  "alarm-clock": AlarmClock,
  "alert-triangle": AlertTriangle,
  "at-sign": AtSign,
  bell: Bell,
  "book-open": BookOpen,
  calendar: Calendar,
  "calendar-check": CalendarCheck,
  "calendar-clock": CalendarClock,
  "calendar-plus": CalendarPlus,
  "calendar-x": CalendarX,
  "check-circle": CheckCircle,
  clock: Clock,
  "file-text": FileText,
  "folder-cog": FolderCog,
  "folder-minus": FolderMinus,
  "folder-plus": FolderPlus,
  "folder-x": FolderX,
  globe: Globe,
  inbox: Inbox,
  lightbulb: Lightbulb,
  lock: Lock,
  mail: Mail,
  "mail-warning": MailWarning,
  megaphone: Megaphone,
  "message-circle": MessageCircle,
  "package-check": PackageCheck,
  "phone-missed": PhoneMissed,
  "refresh-cw": RefreshCw,
  server: Server,
  "shield-alert": ShieldAlert,
  thermometer: Thermometer,
  "trash-2": Trash2,
  unlock: Unlock,
  "user-minus": UserMinus,
  "user-plus": UserPlus,
  "x-circle": XCircle
};
function inboxIcon(name) {
  return ICONS[name] ?? Bell;
}
function bekannteSymbole() {
  return Object.keys(ICONS);
}
var TEXT = {
  blue: "text-blue-600 dark:text-blue-400",
  cyan: "text-cyan-600 dark:text-cyan-400",
  gray: "text-gray-600 dark:text-gray-400",
  green: "text-green-600 dark:text-green-400",
  indigo: "text-indigo-600 dark:text-indigo-400",
  orange: "text-orange-600 dark:text-orange-400",
  purple: "text-purple-600 dark:text-purple-400",
  red: "text-red-600 dark:text-red-400",
  teal: "text-teal-600 dark:text-teal-400",
  yellow: "text-yellow-600 dark:text-yellow-400"
};
var SURFACE = {
  blue: "bg-blue-50 border-blue-100 dark:bg-blue-950 dark:border-blue-900",
  cyan: "bg-cyan-50 border-cyan-100 dark:bg-cyan-950 dark:border-cyan-900",
  gray: "bg-gray-50 border-gray-100 dark:bg-gray-950 dark:border-gray-900",
  green: "bg-green-50 border-green-100 dark:bg-green-950 dark:border-green-900",
  indigo: "bg-indigo-50 border-indigo-100 dark:bg-indigo-950 dark:border-indigo-900",
  orange: "bg-orange-50 border-orange-100 dark:bg-orange-950 dark:border-orange-900",
  purple: "bg-purple-50 border-purple-100 dark:bg-purple-950 dark:border-purple-900",
  red: "bg-red-50 border-red-100 dark:bg-red-950 dark:border-red-900",
  teal: "bg-teal-50 border-teal-100 dark:bg-teal-950 dark:border-teal-900",
  yellow: "bg-yellow-50 border-yellow-100 dark:bg-yellow-950 dark:border-yellow-900"
};
function inboxTextClass(color) {
  return TEXT[color] ?? TEXT.gray;
}
function inboxSurfaceClass(color) {
  return SURFACE[color] ?? SURFACE.gray;
}
function bekannteFarben() {
  return Object.keys(TEXT);
}

// resources/js/src/InboxList.tsx
import { jsx as jsx2, jsxs } from "react/jsx-runtime";
function InboxList({ items, formatTime, localLabel, sourceLabels, onOpen, renderActions, empty }) {
  if (items.length === 0) {
    return /* @__PURE__ */ jsx2("div", { className: "rounded-lg border border-dashed p-8 text-center text-sm text-muted-foreground", children: empty ?? "Nichts offen." });
  }
  return /* @__PURE__ */ jsx2("div", { className: "space-y-2", children: items.map((item) => /* @__PURE__ */ jsx2(
    Zeile,
    {
      item,
      formatTime,
      localLabel,
      sourceLabels,
      onOpen,
      renderActions
    },
    `${item.source ?? "hier"}:${item.kind}:${item.id}`
  )) });
}
function Zeile({
  item,
  formatTime,
  localLabel,
  sourceLabels,
  onOpen,
  renderActions
}) {
  const Symbol = inboxIcon(item.icon);
  const frisch = item.is_unread;
  const herkunft = item.source ? item.source_label ?? sourceLabels[item.source] ?? item.source : localLabel;
  const zusatz = Object.entries(item.meta ?? {}).filter(([, wert]) => wert !== null && wert !== "" && typeof wert !== "object").map(([, wert]) => String(wert));
  return /* @__PURE__ */ jsx2("div", { className: cn("group rounded-lg border p-3", frisch ? inboxSurfaceClass(item.color) : "transition-colors hover:bg-muted/30"), children: /* @__PURE__ */ jsxs("div", { className: "flex items-start gap-3", children: [
    /* @__PURE__ */ jsx2("div", { className: cn("mt-0.5 shrink-0 rounded-full p-1.5", frisch ? inboxSurfaceClass(item.color) : "bg-muted"), children: /* @__PURE__ */ jsx2(Symbol, { className: cn("h-4 w-4", frisch ? inboxTextClass(item.color) : "text-muted-foreground") }) }),
    /* @__PURE__ */ jsxs("div", { className: "min-w-0 flex-1 cursor-pointer", onClick: () => onOpen(item), children: [
      /* @__PURE__ */ jsxs("div", { className: "flex flex-wrap items-center gap-2", children: [
        /* @__PURE__ */ jsx2("p", { className: cn("font-medium hover:underline", frisch ? inboxTextClass(item.color) : "truncate"), children: item.title }),
        /* @__PURE__ */ jsx2(Badge, { variant: "outline", children: herkunft })
      ] }),
      item.message && /* @__PURE__ */ jsx2("p", { className: "mt-1 line-clamp-2 text-xs text-muted-foreground", children: item.message }),
      /* @__PURE__ */ jsxs("div", { className: "mt-1 flex flex-wrap items-center gap-2 text-xs text-muted-foreground", children: [
        item.created_at && /* @__PURE__ */ jsx2("span", { children: formatTime(item.created_at) }),
        zusatz.map((wert, i) => /* @__PURE__ */ jsxs("span", { className: "truncate", children: [
          "\xB7 ",
          wert
        ] }, i))
      ] })
    ] }),
    /* @__PURE__ */ jsx2("div", { className: "flex shrink-0 items-center gap-1", children: renderActions?.(item) })
  ] }) });
}

// resources/js/src/InboxPage.tsx
import { Settings as Zahnrad } from "lucide-react";
import { jsx as jsx3, jsxs as jsxs2 } from "react/jsx-runtime";
function InboxPage({
  items,
  kinds,
  counts,
  unreachable,
  failed,
  labels,
  formatTime,
  onOpen,
  renderActions,
  initialKind,
  settingsHref = "/settings/notifications",
  title = "Posteingang",
  subtitle = "Alles, was auf dich wartet \u2014 aus allen Systemen"
}) {
  const ersterMitInhalt = useMemo(
    () => kinds.find((k) => (counts[k.key] ?? 0) > 0)?.key ?? kinds[0]?.key ?? "",
    [kinds, counts]
  );
  const [offen, setOffen] = useState(initialKind ?? ersterMitInhalt);
  const sichtbar = useMemo(() => items.filter((i) => i.kind === offen), [items, offen]);
  return /* @__PURE__ */ jsxs2("div", { className: "space-y-4", children: [
    /* @__PURE__ */ jsxs2("div", { className: "flex flex-wrap items-start justify-between gap-3", children: [
      /* @__PURE__ */ jsxs2("div", { children: [
        /* @__PURE__ */ jsx3("h1", { className: "text-2xl font-semibold", children: title }),
        /* @__PURE__ */ jsx3("p", { className: "text-sm text-muted-foreground", children: subtitle })
      ] }),
      settingsHref !== null && /* @__PURE__ */ jsxs2(
        "a",
        {
          href: settingsHref,
          className: "inline-flex shrink-0 items-center gap-2 rounded-md border px-3 py-2 text-sm text-muted-foreground transition-colors hover:bg-muted/50 hover:text-foreground",
          children: [
            /* @__PURE__ */ jsx3(Zahnrad, { className: "h-4 w-4" }),
            /* @__PURE__ */ jsx3("span", { children: "Einstellungen" })
          ]
        }
      )
    ] }),
    (unreachable.length > 0 || failed.length > 0) && // Eine still verkuerzte Liste sieht aus wie „da ist
    // nichts" — und dann sucht man im falschen System.
    /* @__PURE__ */ jsxs2("div", { className: "rounded-md bg-warning-subtle px-3 py-2 text-sm text-warning-foreground", children: [
      unreachable.length > 0 && /* @__PURE__ */ jsxs2("p", { children: [
        "Nicht erreichbar: ",
        unreachable.join(", "),
        ". Von dort fehlen gerade Eintr\xE4ge."
      ] }),
      failed.length > 0 && /* @__PURE__ */ jsxs2("p", { children: [
        "Hier konnte gerade nicht geladen werden: ",
        failed.join(", "),
        "."
      ] })
    ] }),
    /* @__PURE__ */ jsx3("div", { className: "flex flex-wrap gap-1 rounded-lg bg-muted/50 p-1", role: "tablist", children: kinds.map((art) => {
      const Symbol = inboxIcon(art.icon);
      const zahl = counts[art.key] ?? 0;
      const aktiv = art.key === offen;
      return /* @__PURE__ */ jsxs2(
        "button",
        {
          type: "button",
          role: "tab",
          "aria-selected": aktiv,
          onClick: () => setOffen(art.key),
          className: cn(
            // KEIN `flex-1`: Bei sechs Reitern quetscht das
            // jeden auf gleiche Breite, und der Zaehler wird
            // zu einem leeren roten Punkt zusammengedrueckt.
            // Genau so sah es im ersten Browser-Durchgang aus.
            "flex items-center gap-2 rounded-md px-3 py-2 text-sm whitespace-nowrap transition-colors",
            aktiv ? "bg-background shadow-sm" : "text-muted-foreground hover:text-foreground"
          ),
          children: [
            /* @__PURE__ */ jsx3(Symbol, { className: "h-4 w-4 shrink-0" }),
            /* @__PURE__ */ jsx3("span", { children: art.label }),
            zahl > 0 && // `text-white`, nicht `text-destructive-foreground`:
            // Das ist in den Produkt-Themes nicht gesetzt und
            // erbt dann das Grau des Reiters — Grau auf Rot bei
            // fast gleicher Helligkeit. Die Zahl stand da und
            // war trotzdem unsichtbar; gefunden erst beim
            // Nachmessen der berechneten Farbe, nicht beim
            // Hinsehen.
            /* @__PURE__ */ jsx3("span", { className: "inline-flex min-w-5 shrink-0 items-center justify-center rounded-full bg-destructive px-1.5 py-0.5 text-xs leading-none font-medium text-white tabular-nums", children: zahl })
          ]
        },
        art.key
      );
    }) }),
    /* @__PURE__ */ jsx3("div", { role: "tabpanel", children: /* @__PURE__ */ jsx3(
      InboxList,
      {
        items: sichtbar,
        formatTime,
        localLabel: labels.local,
        sourceLabels: labels.sources,
        onOpen,
        renderActions
      }
    ) })
  ] });
}
export {
  InboxList,
  InboxPage,
  bekannteFarben,
  bekannteSymbole,
  inboxIcon,
  inboxSurfaceClass,
  inboxTextClass
};
