// resources/js/src/InboxPage.tsx
import { useMemo, useState as useState3 } from "react";

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
  const hatZiel = typeof item.link === "string" && item.link !== "";
  return /* @__PURE__ */ jsx2(
    "div",
    {
      className: cn(
        "group rounded-lg border p-3",
        frisch ? inboxSurfaceClass(item.color) : "transition-colors hover:bg-muted/30"
      ),
      children: /* @__PURE__ */ jsxs("div", { className: "flex items-start gap-3", children: [
        /* @__PURE__ */ jsx2(
          "div",
          {
            className: cn(
              "mt-0.5 shrink-0 rounded-full p-1.5",
              frisch ? inboxSurfaceClass(item.color) : "bg-muted"
            ),
            children: /* @__PURE__ */ jsx2(Symbol, { className: cn("h-4 w-4", frisch ? inboxTextClass(item.color) : "text-muted-foreground") })
          }
        ),
        /* @__PURE__ */ jsxs(
          "div",
          {
            className: cn("min-w-0 flex-1", hatZiel ? "cursor-pointer" : "cursor-default"),
            onClick: () => onOpen(item),
            children: [
              /* @__PURE__ */ jsxs("div", { className: "flex flex-wrap items-center gap-2", children: [
                /* @__PURE__ */ jsx2(
                  "p",
                  {
                    className: cn(
                      "font-medium",
                      hatZiel && "hover:underline",
                      frisch ? inboxTextClass(item.color) : "truncate"
                    ),
                    children: item.title
                  }
                ),
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
            ]
          }
        ),
        /* @__PURE__ */ jsx2("div", { className: "flex shrink-0 items-center gap-1", children: renderActions?.(item) })
      ] })
    }
  );
}

// resources/js/src/InboxPage.tsx
import { Check, CheckCheck, RefreshCw as RefreshCw2, Settings as Zahnrad, Trash2 as Trash22 } from "lucide-react";

// resources/js/src/abhaken.ts
function zeilenSchluessel(item) {
  return `${item.source ?? ""}:${item.kind}:${item.id}`;
}
function darfAbhaken(item, markable) {
  if (!item.is_unread || !markable) {
    return false;
  }
  const arten = item.source ? markable.sources?.[item.source] ?? [] : markable.local ?? [];
  return arten.includes(item.kind);
}
function istEigenAbhakbar(item, markable) {
  return !item.source && darfAbhaken(item, markable);
}
function nochUngelesen(item, abgehakt, alleEigenenAbgehakt, markable) {
  if (!item.is_unread || abgehakt.has(zeilenSchluessel(item))) {
    return false;
  }
  return !(alleEigenenAbgehakt && istEigenAbhakbar(item, markable));
}
function bestandJeArt(totals) {
  if (!totals) {
    return {};
  }
  const summe = { ...totals.local ?? {} };
  for (const jeArt of Object.values(totals.sources ?? {})) {
    for (const [art, zahl] of Object.entries(jeArt ?? {})) {
      summe[art] = (summe[art] ?? 0) + zahl;
    }
  }
  return summe;
}
function eigenerBestandAbhakbar(totals, markable) {
  if (!totals || !markable) {
    return 0;
  }
  return (markable.local ?? []).reduce((summe, art) => summe + (totals.local?.[art] ?? 0), 0);
}
function hinweisZurListe(angezeigt, bestand) {
  if (bestand <= angezeigt) {
    return null;
  }
  return `Hier stehen die ${angezeigt} neuesten Zeilen von ${bestand} ungelesenen.`;
}

// resources/js/src/loeschen.ts
function darfLoeschen(item, deletable) {
  if (item.source || !deletable) {
    return false;
  }
  return (deletable.local ?? []).includes(item.kind);
}
function artLoeschbar(art, deletable) {
  return (deletable?.local ?? []).includes(art);
}
function eigenerBestandDerArt(art, totals) {
  return totals?.local?.[art] ?? 0;
}
function loeschFrage(wort, bestand, angezeigt) {
  const zahl = bestand > 0 ? bestand : angezeigt;
  return zahl > 0 ? `${zahl} ${wort} endg\xFCltig l\xF6schen? Das l\xE4sst sich nicht zur\xFCcknehmen.` : `Alle ${wort} endg\xFCltig l\xF6schen? Das l\xE4sst sich nicht zur\xFCcknehmen.`;
}

// resources/js/src/useAbhaken.ts
import { useCallback, useState } from "react";

// resources/js/src/csrf.ts
function csrfHeaders() {
  if (typeof document === "undefined") {
    return {};
  }
  const meta = document.querySelector('meta[name="csrf-token"]')?.getAttribute("content");
  if (meta) {
    return { "X-CSRF-TOKEN": meta };
  }
  const cookie = document.cookie.split("; ").find((teil) => teil.startsWith("XSRF-TOKEN="))?.slice("XSRF-TOKEN=".length);
  return cookie ? { "X-XSRF-TOKEN": decodeURIComponent(cookie) } : {};
}

// resources/js/src/useAbhaken.ts
function useAbhaken(options = {}) {
  const { href = "/posteingang/gelesen", onChanged } = options;
  const [abgehakt, setAbgehakt] = useState(() => /* @__PURE__ */ new Set());
  const [alleEigenenAbgehakt, setAlleEigenenAbgehakt] = useState(false);
  const [laeuft, setLaeuft] = useState(false);
  const [fehler, setFehler] = useState(null);
  const schreiben = useCallback(
    async (nutzlast) => {
      if (!href) {
        return false;
      }
      setLaeuft(true);
      setFehler(null);
      try {
        const antwort = await fetch(href, {
          method: "POST",
          headers: {
            "Content-Type": "application/json",
            Accept: "application/json",
            "X-Requested-With": "XMLHttpRequest",
            ...csrfHeaders()
          },
          body: JSON.stringify(nutzlast)
        });
        if (antwort.status === 419) {
          window.location.reload();
          return false;
        }
        if (!antwort.ok) {
          const daten = await antwort.json().catch(() => null);
          setFehler(daten?.message ?? `Das Abhaken kam nicht an (${antwort.status}).`);
          return false;
        }
        onChanged?.();
        return true;
      } catch (ursache) {
        setFehler(ursache instanceof Error ? ursache.message : "Das Abhaken kam nicht an.");
        return false;
      } finally {
        setLaeuft(false);
      }
    },
    [href, onChanged]
  );
  const abhaken = useCallback(
    async (zeilen) => {
      if (zeilen.length === 0) {
        return;
      }
      const erste = zeilen[0];
      if (!erste) {
        return;
      }
      const gleiche = zeilen.filter(
        (z) => z.kind === erste.kind && (z.source ?? null) === (erste.source ?? null)
      );
      const erfolg = await schreiben({
        source: erste.source ?? null,
        kind: erste.kind,
        ids: gleiche.map((z) => z.id)
      });
      if (erfolg) {
        setAbgehakt((bisher) => {
          const neu = new Set(bisher);
          gleiche.forEach((z) => neu.add(zeilenSchluessel(z)));
          return neu;
        });
      }
      const rest = zeilen.filter((z) => !gleiche.includes(z));
      if (rest.length > 0) {
        await abhaken(rest);
      }
    },
    [schreiben]
  );
  const alleEigenen = useCallback(async () => {
    if (await schreiben({ alle: true })) {
      setAlleEigenenAbgehakt(true);
    }
  }, [schreiben]);
  return {
    abgehakt,
    alleEigenenAbgehakt,
    laeuft,
    fehler,
    abhaken,
    alleEigenen
  };
}

// resources/js/src/useLoeschen.ts
import { useCallback as useCallback2, useState as useState2 } from "react";
function useLoeschen(options = {}) {
  const { href = "/posteingang/zeilen", onChanged } = options;
  const [geloescht, setGeloescht] = useState2(() => /* @__PURE__ */ new Set());
  const [geleerteArten, setGeleerteArten] = useState2(() => /* @__PURE__ */ new Set());
  const [laeuft, setLaeuft] = useState2(false);
  const [fehler, setFehler] = useState2(null);
  const schreiben = useCallback2(
    async (nutzlast) => {
      if (!href) {
        return false;
      }
      setLaeuft(true);
      setFehler(null);
      try {
        const antwort = await fetch(href, {
          method: "DELETE",
          headers: {
            "Content-Type": "application/json",
            Accept: "application/json",
            "X-Requested-With": "XMLHttpRequest",
            ...csrfHeaders()
          },
          body: JSON.stringify(nutzlast)
        });
        if (antwort.status === 419) {
          window.location.reload();
          return false;
        }
        if (!antwort.ok) {
          const daten = await antwort.json().catch(() => null);
          setFehler(daten?.message ?? `Das L\xF6schen kam nicht an (${antwort.status}).`);
          return false;
        }
        onChanged?.();
        return true;
      } catch (ursache) {
        setFehler(ursache instanceof Error ? ursache.message : "Das L\xF6schen kam nicht an.");
        return false;
      } finally {
        setLaeuft(false);
      }
    },
    [href, onChanged]
  );
  const loeschen = useCallback2(
    async (zeilen) => {
      if (zeilen.length === 0) {
        return;
      }
      const erste = zeilen[0];
      if (!erste) {
        return;
      }
      const gleiche = zeilen.filter((z) => z.kind === erste.kind && !z.source);
      if (gleiche.length > 0) {
        const erfolg = await schreiben({
          kind: erste.kind,
          ids: gleiche.map((z) => z.id)
        });
        if (erfolg) {
          setGeloescht((bisher) => {
            const neu = new Set(bisher);
            gleiche.forEach((z) => neu.add(zeilenSchluessel(z)));
            return neu;
          });
        }
      }
      const rest = zeilen.filter((z) => !gleiche.includes(z));
      if (rest.length > 0) {
        await loeschen(rest);
      }
    },
    [schreiben]
  );
  const alleDerArt = useCallback2(
    async (art) => {
      if (await schreiben({ alle: true, kind: art })) {
        setGeleerteArten((bisher) => new Set(bisher).add(art));
      }
    },
    [schreiben]
  );
  return { geloescht, geleerteArten, laeuft, fehler, loeschen, alleDerArt };
}

// resources/js/src/InboxPage.tsx
import { Fragment, jsx as jsx3, jsxs as jsxs2 } from "react/jsx-runtime";
function InboxPage({
  items,
  kinds,
  counts,
  totals,
  markable,
  deletable,
  unreachable,
  failed,
  labels,
  formatTime,
  onOpen,
  renderActions,
  initialKind,
  settingsHref = "/settings/notifications",
  readHref = "/posteingang/gelesen",
  onMarked,
  deleteHref = "/posteingang/zeilen",
  onDeleted,
  onRefresh,
  confirm: bestaetigen,
  title = "Posteingang",
  subtitle = "Alles, was auf dich wartet \u2014 aus allen Systemen"
}) {
  const haken = useAbhaken({ href: readHref, onChanged: onMarked });
  const papierkorb = useLoeschen({ href: deleteHref, onChanged: onDeleted });
  const fragen = bestaetigen ?? ((frage) => typeof window === "undefined" ? false : window.confirm(frage));
  const [nurUngelesen, setNurUngelesen] = useState3(false);
  const [herkunft, setHerkunft] = useState3(null);
  const ersterMitInhalt = useMemo(
    () => kinds.find((k) => (counts[k.key] ?? 0) > 0)?.key ?? kinds[0]?.key ?? "",
    [kinds, counts]
  );
  const [offen, setOffen] = useState3(initialKind ?? ersterMitInhalt);
  const zeilen = useMemo(
    () => items.filter(
      (item) => !papierkorb.geloescht.has(zeilenSchluessel(item)) && !(papierkorb.geleerteArten.has(item.kind) && !item.source)
    ).map((item) => ({
      ...item,
      is_unread: nochUngelesen(item, haken.abgehakt, haken.alleEigenenAbgehakt, markable)
    })),
    [items, haken.abgehakt, haken.alleEigenenAbgehakt, markable, papierkorb.geloescht, papierkorb.geleerteArten]
  );
  const zahlen = useMemo(() => {
    const angepasst = { ...counts };
    const nachSchluessel = new Map(zeilen.map((z) => [zeilenSchluessel(z), z]));
    items.forEach((item) => {
      if (!item.is_unread) {
        return;
      }
      const noch = nachSchluessel.get(zeilenSchluessel(item));
      if (!noch || noch.is_unread === false) {
        angepasst[item.kind] = Math.max(0, (angepasst[item.kind] ?? 0) - 1);
      }
    });
    return angepasst;
  }, [counts, items, zeilen]);
  const derArt = useMemo(() => zeilen.filter((i) => i.kind === offen), [zeilen, offen]);
  const systeme = useMemo(() => {
    const vorhanden = /* @__PURE__ */ new Map();
    derArt.forEach((item) => {
      const schluessel = item.source ?? "";
      vorhanden.set(
        schluessel,
        item.source ? item.source_label ?? labels.sources[item.source] ?? item.source : labels.local
      );
    });
    return [...vorhanden.entries()];
  }, [derArt, labels]);
  const sichtbar = useMemo(
    () => derArt.filter(
      (item) => (!nurUngelesen || item.is_unread) && (herkunft === null || (item.source ?? "") === herkunft)
    ),
    [derArt, nurUngelesen, herkunft]
  );
  const neue = useMemo(() => sichtbar.filter((i) => i.is_unread), [sichtbar]);
  const frueher = useMemo(() => sichtbar.filter((i) => !i.is_unread), [sichtbar]);
  const gruppiert = neue.length > 0 && frueher.length > 0;
  const eigeneOffen = haken.alleEigenenAbgehakt ? 0 : eigenerBestandAbhakbar(totals, markable);
  const satz = hinweisZurListe(derArt.length, bestandJeArt(totals)[offen] ?? 0);
  const offeneArt = kinds.find((k) => k.key === offen);
  const darfArtLeeren = deleteHref !== null && artLoeschbar(offen, deletable) && !papierkorb.geleerteArten.has(offen);
  const bestandDerArt = eigenerBestandDerArt(offen, totals);
  const wortDerArt = offeneArt?.label ?? "Eintr\xE4ge";
  const oeffnenMitHaken = (eintrag) => {
    if (readHref !== null && darfAbhaken(eintrag, markable)) {
      void haken.abhaken([eintrag]);
    }
    onOpen(eintrag);
  };
  const zeilenAktionen = (eintrag) => /* @__PURE__ */ jsxs2(Fragment, { children: [
    readHref !== null && darfAbhaken(eintrag, markable) && /* @__PURE__ */ jsx3(
      "button",
      {
        type: "button",
        title: "Als gelesen markieren",
        "aria-label": "Als gelesen markieren",
        disabled: haken.laeuft,
        onClick: (event) => {
          event.stopPropagation();
          void haken.abhaken([eintrag]);
        },
        className: "inline-flex h-8 w-8 items-center justify-center rounded-md text-muted-foreground transition-colors hover:bg-muted hover:text-foreground disabled:opacity-60",
        children: /* @__PURE__ */ jsx3(Check, { className: "h-4 w-4" })
      }
    ),
    deleteHref !== null && darfLoeschen(eintrag, deletable) && /* @__PURE__ */ jsx3(
      "button",
      {
        type: "button",
        title: "L\xF6schen",
        "aria-label": "L\xF6schen",
        disabled: papierkorb.laeuft,
        onClick: (event) => {
          event.stopPropagation();
          void papierkorb.loeschen([eintrag]);
        },
        className: "inline-flex h-8 w-8 items-center justify-center rounded-md text-muted-foreground transition-colors hover:bg-destructive/10 hover:text-destructive disabled:opacity-60",
        children: /* @__PURE__ */ jsx3(Trash22, { className: "h-4 w-4" })
      }
    ),
    renderActions?.(eintrag)
  ] });
  const listenGrundlage = {
    formatTime,
    localLabel: labels.local,
    sourceLabels: labels.sources,
    onOpen: oeffnenMitHaken,
    renderActions: zeilenAktionen
  };
  return /* @__PURE__ */ jsxs2("div", { className: "space-y-4", children: [
    /* @__PURE__ */ jsxs2("div", { className: "flex flex-wrap items-start justify-between gap-3", children: [
      /* @__PURE__ */ jsxs2("div", { children: [
        /* @__PURE__ */ jsx3("h1", { className: "text-2xl font-semibold", children: title }),
        /* @__PURE__ */ jsx3("p", { className: "text-sm text-muted-foreground", children: subtitle })
      ] }),
      /* @__PURE__ */ jsxs2("div", { className: "flex shrink-0 flex-wrap items-center gap-2", children: [
        readHref !== null && eigeneOffen > 0 && /* @__PURE__ */ jsxs2(
          "button",
          {
            type: "button",
            disabled: haken.laeuft,
            onClick: () => void haken.alleEigenen(),
            className: "inline-flex items-center gap-2 rounded-md border px-3 py-2 text-sm text-muted-foreground transition-colors hover:bg-muted/50 hover:text-foreground disabled:opacity-60",
            children: [
              /* @__PURE__ */ jsx3(CheckCheck, { className: "h-4 w-4" }),
              /* @__PURE__ */ jsxs2("span", { children: [
                "Alle ",
                eigeneOffen,
                " eigenen als gelesen"
              ] })
            ]
          }
        ),
        darfArtLeeren && derArt.some((i) => !i.source) && /* @__PURE__ */ jsxs2(
          "button",
          {
            type: "button",
            disabled: papierkorb.laeuft,
            onClick: () => {
              void (async () => {
                const frage = loeschFrage(
                  wortDerArt,
                  bestandDerArt,
                  derArt.filter((i) => !i.source).length
                );
                if (await fragen(frage)) {
                  await papierkorb.alleDerArt(offen);
                }
              })();
            },
            className: "inline-flex items-center gap-2 rounded-md border border-destructive/40 px-3 py-2 text-sm text-destructive transition-colors hover:bg-destructive/10 disabled:opacity-60",
            children: [
              /* @__PURE__ */ jsx3(Trash22, { className: "h-4 w-4" }),
              /* @__PURE__ */ jsxs2("span", { children: [
                "Alle meine ",
                wortDerArt,
                " l\xF6schen"
              ] })
            ]
          }
        ),
        onRefresh && /* @__PURE__ */ jsx3(
          "button",
          {
            type: "button",
            title: "Neu laden",
            "aria-label": "Neu laden",
            onClick: () => onRefresh(),
            className: "inline-flex items-center gap-2 rounded-md border px-3 py-2 text-sm text-muted-foreground transition-colors hover:bg-muted/50 hover:text-foreground",
            children: /* @__PURE__ */ jsx3(RefreshCw2, { className: "h-4 w-4" })
          }
        ),
        settingsHref !== null && /* @__PURE__ */ jsxs2(
          "a",
          {
            href: settingsHref,
            className: "inline-flex items-center gap-2 rounded-md border px-3 py-2 text-sm text-muted-foreground transition-colors hover:bg-muted/50 hover:text-foreground",
            children: [
              /* @__PURE__ */ jsx3(Zahnrad, { className: "h-4 w-4" }),
              /* @__PURE__ */ jsx3("span", { children: "Einstellungen" })
            ]
          }
        )
      ] })
    ] }),
    haken.fehler !== null && // Wer abhakt und es kommt nicht an, muss das sehen. Sonst
    // steht die Zeile beim Neuladen wieder da — und der Fehler
    // sieht aus wie der Fix.
    /* @__PURE__ */ jsxs2("div", { role: "alert", className: "rounded-md bg-destructive/10 px-3 py-2 text-sm text-destructive", children: [
      "Das Abhaken kam nicht an: ",
      haken.fehler
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
      const zahl = zahlen[art.key] ?? 0;
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
    (derArt.length > 0 || nurUngelesen || herkunft !== null) && /* @__PURE__ */ jsxs2("div", { className: "flex flex-wrap items-center gap-2", children: [
      /* @__PURE__ */ jsx3(
        "button",
        {
          type: "button",
          "aria-pressed": nurUngelesen,
          onClick: () => setNurUngelesen((an) => !an),
          className: cn(
            "rounded-md border px-3 py-1.5 text-sm transition-colors",
            nurUngelesen ? "border-transparent bg-primary text-primary-foreground" : "text-muted-foreground hover:bg-muted/50 hover:text-foreground"
          ),
          children: "Nur ungelesene"
        }
      ),
      systeme.length > 1 && /* @__PURE__ */ jsxs2("div", { className: "flex flex-wrap items-center gap-1", children: [
        /* @__PURE__ */ jsx3(
          "button",
          {
            type: "button",
            "aria-pressed": herkunft === null,
            onClick: () => setHerkunft(null),
            className: cn(
              "rounded-md border px-3 py-1.5 text-sm transition-colors",
              herkunft === null ? "border-transparent bg-primary text-primary-foreground" : "text-muted-foreground hover:bg-muted/50 hover:text-foreground"
            ),
            children: "Alle Systeme"
          }
        ),
        systeme.map(([schluessel, wort]) => /* @__PURE__ */ jsx3(
          "button",
          {
            type: "button",
            "aria-pressed": herkunft === schluessel,
            onClick: () => setHerkunft(herkunft === schluessel ? null : schluessel),
            className: cn(
              "rounded-md border px-3 py-1.5 text-sm transition-colors",
              herkunft === schluessel ? "border-transparent bg-primary text-primary-foreground" : "text-muted-foreground hover:bg-muted/50 hover:text-foreground"
            ),
            children: wort
          },
          schluessel || "hier"
        ))
      ] })
    ] }),
    papierkorb.fehler !== null && /* @__PURE__ */ jsx3("div", { className: "rounded-md bg-destructive/10 px-3 py-2 text-sm text-destructive", children: papierkorb.fehler }),
    /* @__PURE__ */ jsx3("div", { role: "tabpanel", className: "space-y-4", children: gruppiert ? /* @__PURE__ */ jsxs2(Fragment, { children: [
      /* @__PURE__ */ jsxs2("div", { className: "space-y-2", children: [
        /* @__PURE__ */ jsxs2("p", { className: "text-xs font-semibold text-muted-foreground", children: [
          "Neu (",
          neue.length,
          ")"
        ] }),
        /* @__PURE__ */ jsx3(InboxList, { items: neue, ...listenGrundlage })
      ] }),
      /* @__PURE__ */ jsxs2("div", { className: "space-y-2", children: [
        /* @__PURE__ */ jsxs2("p", { className: "text-xs font-semibold text-muted-foreground", children: [
          "Fr\xFCher (",
          frueher.length,
          ")"
        ] }),
        /* @__PURE__ */ jsx3(InboxList, { items: frueher, ...listenGrundlage })
      ] })
    ] }) : /* @__PURE__ */ jsx3(
      InboxList,
      {
        items: sichtbar,
        ...listenGrundlage,
        empty: (
          // Ein Filter, der nichts uebrig laesst, sieht
          // sonst aus wie ein leerer Posteingang — und man
          // sucht den Fehler bei sich statt beim Filter.
          derArt.length > 0 ? "Nichts, das zu diesem Filter passt." : void 0
        )
      }
    ) }),
    satz !== null && /* @__PURE__ */ jsx3("p", { className: "text-center text-xs text-muted-foreground", children: satz })
  ] });
}
export {
  InboxList,
  InboxPage,
  artLoeschbar,
  bekannteFarben,
  bekannteSymbole,
  bestandJeArt,
  csrfHeaders,
  darfAbhaken,
  darfLoeschen,
  eigenerBestandAbhakbar,
  eigenerBestandDerArt,
  hinweisZurListe,
  inboxIcon,
  inboxSurfaceClass,
  inboxTextClass,
  istEigenAbhakbar,
  loeschFrage,
  nochUngelesen,
  useAbhaken,
  useLoeschen,
  zeilenSchluessel
};
