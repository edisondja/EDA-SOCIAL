/**
 * Simulador de cuadre: CXC_PES ↔ TAJUSTE ↔ TAJUDET ↔ Remesas
 * Edita TAJUDET / CXC_PES / Remesa y observa el impacto en cadena.
 */

const money = (n) => {
  const v = Number(n) || 0;
  const abs = Math.abs(v).toLocaleString("es-DO", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  });
  return v < 0 ? `(${abs})` : abs;
};

const parseMoney = (raw) => {
  if (raw === null || raw === undefined || raw === "") return 0;
  if (typeof raw === "number") return raw;
  let s = String(raw).trim();
  const neg = /^\(.*\)$/.test(s) || s.startsWith("-");
  s = s.replace(/[()$\s]/g, "").replace(/,/g, "");
  const n = Number(s);
  if (Number.isNaN(n)) return 0;
  return neg && n > 0 ? -n : n;
};

const round2 = (n) => Math.round((Number(n) + Number.EPSILON) * 100) / 100;
const nearlyEq = (a, b, eps = 0.02) => Math.abs(round2(a) - round2(b)) <= eps;

/** Escenario base (datos de la captura) */
function baseScenario() {
  return {
    name: "base",
    cxc: {
      FACTURA: 12345,
      CLIENTE: 1,
      COMPANIA: 1,
      RAMO: 95,
      SECUENCIAL: 25,
      FEC_INI: "2026-01-01",
      FEC_FIN: "2026-01-31",
      VAL_NET: 21666645.1,
      DES_NET: 0,
      IMPT_NET: 433332.9,
      // FACTURA$ y PTIMPORT2 / BALANCE se recalculan
    },
    tajuste: {
      NMAJUSTE: 4,
      CLIENTE: 1,
      COMPANIA: 1,
      RAMO: 95,
      SECUENCIAL: 25,
      TIP_DOC: "FT",
      NMDOCUM: "XXX",
      NMCUOREC: 12345,
      // PTIMPORT se recalcula desde TAJUDET
    },
    tajudet: [
      {
        NMAJUDET: 1,
        NMAJUSTE: 4,
        COMPANIA: 1,
        RAMO: 95,
        SECUENCIAL: 25,
        TIPO_MOV: "E",
        CDTIPDOC: "NC",
        TIPO_ASE: "",
        ASEGURADO: "",
        DEPENDIENTE: "",
        PTIMPORT: 0,
      },
      {
        NMAJUDET: 2,
        NMAJUSTE: 4,
        COMPANIA: 1,
        RAMO: 95,
        SECUENCIAL: 25,
        TIPO_MOV: "I",
        CDTIPDOC: "FT",
        TIPO_ASE: "",
        ASEGURADO: "",
        DEPENDIENTE: "",
        PTIMPORT: 1513072.14,
      },
      {
        NMAJUDET: 3,
        NMAJUSTE: 4,
        COMPANIA: 1,
        RAMO: 95,
        SECUENCIAL: 25,
        TIPO_MOV: "E",
        CDTIPDOC: "NC",
        TIPO_ASE: "",
        ASEGURADO: "",
        DEPENDIENTE: "",
        PTIMPORT: -1431948.63,
      },
    ],
    remesa: {
      CDPERSON: 1,
      CDUNIECO: 1,
      CDRAMO: 95,
      NMPOLFIN: 25,
      NMCUOREC: 12345,
      FOR_PAG: "EFEC",
      PRIMPORT: 0,
    },
  };
}

/**
 * Escenarios predefinidos para practicar cuadre.
 * "targetNet" es el neto de ajuste que se busca (opcional).
 */
const SCENARIOS = [
  {
    id: "captura",
    title: "Datos de captura",
    desc: "Valores del Excel de referencia (neto ≈ 81,123.51).",
    apply: () => structuredClone(baseScenario()),
  },
  {
    id: "cuadrar-82745",
    title: "Cuadrar a (82,745.98)",
    desc: "Σ = −82,745.98 → PTIMPORT2 y BALANCE como en el Excel.",
    apply: () => {
      const s = structuredClone(baseScenario());
      // 1513072.14 + (−1431948.63) + x = −82745.98 → x = −163869.49
      s.tajudet[0].PTIMPORT = -163869.49;
      return s;
    },
  },
  {
    id: "sin-ajuste",
    title: "Sin ajuste",
    desc: "Todas las líneas en 0 → PTIMPORT2 = 0, BALANCE = FACTURA.",
    apply: () => {
      const s = structuredClone(baseScenario());
      s.tajudet.forEach((r) => {
        r.PTIMPORT = 0;
      });
      return s;
    },
  },
  {
    id: "remesa-parcial",
    title: "Remesa parcial",
    desc: "Ajuste cuadrado + pago en remesa = 5,000,000.",
    apply: () => {
      const s = structuredClone(baseScenario());
      s.tajudet[0].PTIMPORT = -163869.49;
      s.remesa.PRIMPORT = 5000000;
      return s;
    },
  },
  {
    id: "descuadre",
    title: "Descuadre intencional",
    desc: "Neto de detalle ≠ cabecera esperada; útil para ver alertas.",
    apply: () => {
      const s = structuredClone(baseScenario());
      s.tajudet[1].PTIMPORT = 2000000;
      s.tajudet[2].PTIMPORT = -1000000;
      return s;
    },
  },
];

let state = structuredClone(baseScenario());
let activeScenario = "captura";

/** Recalcula campos derivados en cadena */
function recompute(s) {
  const sumDet = round2(
    s.tajudet.reduce((acc, r) => acc + (Number(r.PTIMPORT) || 0), 0)
  );

  s.tajuste.PTIMPORT = sumDet;
  s.tajuste.NMCUOREC = s.cxc.FACTURA;
  s.tajuste.CLIENTE = s.cxc.CLIENTE;
  s.tajuste.COMPANIA = s.cxc.COMPANIA;
  s.tajuste.RAMO = s.cxc.RAMO;
  s.tajuste.SECUENCIAL = s.cxc.SECUENCIAL;

  s.tajudet.forEach((r) => {
    r.NMAJUSTE = s.tajuste.NMAJUSTE;
    r.COMPANIA = s.cxc.COMPANIA;
    r.RAMO = s.cxc.RAMO;
    r.SECUENCIAL = s.cxc.SECUENCIAL;
  });

  const facturaAmt = round2(
    (Number(s.cxc.VAL_NET) || 0) -
      (Number(s.cxc.DES_NET) || 0) +
      (Number(s.cxc.IMPT_NET) || 0)
  );
  s.cxc.FACTURA_AMT = facturaAmt;

  // Convención del Excel: PTIMPORT2 refleja el ajuste (mismo signo que TAJUSTE.PTIMPORT)
  // En la captura: PTIMPORT2 = (82,745.98) y BALANCE = FACTURA - PTIMPORT2
  // Si PTIMPORT2 es negativo: BALANCE = FACTURA - (negativo) = FACTURA + |ajuste|
  s.cxc.PTIMPORT2 = sumDet;
  s.cxc.BALANCE = round2(facturaAmt - s.cxc.PTIMPORT2);

  s.remesa.NMCUOREC = s.cxc.FACTURA;
  s.remesa.CDPERSON = s.cxc.CLIENTE;
  s.remesa.CDUNIECO = s.cxc.COMPANIA;
  s.remesa.CDRAMO = s.cxc.RAMO;
  s.remesa.NMPOLFIN = s.cxc.SECUENCIAL;

  // Saldo pendiente tras remesa (informativo)
  s.saldoTrasRemesa = round2(s.cxc.BALANCE - (Number(s.remesa.PRIMPORT) || 0));

  return { sumDet, facturaAmt };
}

function statusChecks(s, sumDet) {
  const checks = [];

  checks.push({
    id: "link-factura",
    label: "Enlace FACTURA / NMCUOREC",
    ok: s.tajuste.NMCUOREC === s.cxc.FACTURA && s.remesa.NMCUOREC === s.cxc.FACTURA,
    value: `FACTURA ${s.cxc.FACTURA}`,
  });

  checks.push({
    id: "sum-cabecera",
    label: "Σ TAJUDET = TAJUSTE.PTIMPORT",
    ok: nearlyEq(sumDet, s.tajuste.PTIMPORT),
    value: money(sumDet),
  });

  checks.push({
    id: "ajuste-cxc",
    label: "TAJUSTE → CXC.PTIMPORT2",
    ok: nearlyEq(s.tajuste.PTIMPORT, s.cxc.PTIMPORT2),
    value: money(s.cxc.PTIMPORT2),
  });

  const expectedBal = round2(s.cxc.FACTURA_AMT - s.cxc.PTIMPORT2);
  checks.push({
    id: "balance",
    label: "BALANCE = FACTURA − PTIMPORT2",
    ok: nearlyEq(s.cxc.BALANCE, expectedBal),
    value: money(s.cxc.BALANCE),
  });

  const targetSheet = 82745.98;
  const matchesSheet = nearlyEq(Math.abs(sumDet), targetSheet);
  checks.push({
    id: "sheet-target",
    label: "¿|neto| ≈ 82,745.98 (Excel)?",
    ok: matchesSheet,
    warn: !matchesSheet,
    value: money(sumDet),
  });

  const excelBalance = round2(s.cxc.FACTURA_AMT - -targetSheet);
  checks.push({
    id: "excel-balance",
    label: "BALANCE tipo Excel (con PTIMPORT2 negativo)",
    ok: nearlyEq(s.cxc.BALANCE, excelBalance) && nearlyEq(s.cxc.PTIMPORT2, -targetSheet),
    warn: !(nearlyEq(s.cxc.BALANCE, excelBalance) && nearlyEq(s.cxc.PTIMPORT2, -targetSheet)),
    value: nearlyEq(s.cxc.PTIMPORT2, -targetSheet)
      ? money(s.cxc.BALANCE)
      : `esperado ${money(excelBalance)}`,
  });

  checks.push({
    id: "remesa",
    label: "Saldo tras remesa (BALANCE − PRIMPORT)",
    ok: true,
    value: money(s.saldoTrasRemesa),
  });

  return checks;
}

/* ---------- Render ---------- */

function el(tag, attrs = {}, children = []) {
  const node = document.createElement(tag);
  Object.entries(attrs).forEach(([k, v]) => {
    if (k === "className") node.className = v;
    else if (k === "text") node.textContent = v;
    else if (k === "html") node.innerHTML = v;
    else if (k.startsWith("on") && typeof v === "function") node.addEventListener(k.slice(2).toLowerCase(), v);
    else if (v !== undefined && v !== null) node.setAttribute(k, v);
  });
  (Array.isArray(children) ? children : [children]).forEach((c) => {
    if (c == null) return;
    node.appendChild(typeof c === "string" ? document.createTextNode(c) : c);
  });
  return node;
}

function moneyInput(value, onChange, extraClass = "") {
  const input = el("input", {
    className: `cell num ${extraClass}`.trim(),
    type: "text",
    value: money(value),
    inputmode: "decimal",
  });
  input.addEventListener("focus", () => {
    input.value = String(round2(parseMoney(input.value)));
    input.select();
  });
  input.addEventListener("blur", () => {
    const n = round2(parseMoney(input.value));
    onChange(n);
    render();
  });
  input.addEventListener("keydown", (e) => {
    if (e.key === "Enter") input.blur();
  });
  return input;
}

function textInput(value, onChange, opts = {}) {
  const input = el("input", {
    className: `cell ${opts.className || ""}`.trim(),
    type: opts.type || "text",
    value: value ?? "",
  });
  if (opts.readonly) {
    input.readOnly = true;
    input.classList.add("readonly");
  }
  input.addEventListener("change", () => {
    onChange(opts.type === "number" ? Number(input.value) : input.value);
    render();
  });
  return input;
}

function selectInput(value, options, onChange) {
  const sel = el("select", { className: "cell" });
  options.forEach((o) => {
    const opt = el("option", { value: o, text: o || "—" });
    if (o === value) opt.selected = true;
    sel.appendChild(opt);
  });
  sel.addEventListener("change", () => {
    onChange(sel.value);
    render();
  });
  return sel;
}

function td(content, className = "") {
  const cell = el("td", { className });
  if (typeof content === "string" || typeof content === "number") {
    cell.textContent = content;
  } else if (content) {
    cell.appendChild(content);
  }
  return cell;
}

function renderCxc() {
  const tbody = document.querySelector("#tableCxc tbody");
  tbody.innerHTML = "";
  const c = state.cxc;
  const tr = el("tr");

  tr.appendChild(td(textInput(c.FACTURA, (v) => { state.cxc.FACTURA = Number(v) || 0; }, { type: "number" })));
  tr.appendChild(td(textInput(c.CLIENTE, (v) => { state.cxc.CLIENTE = Number(v) || 0; }, { type: "number" })));
  tr.appendChild(td(textInput(c.COMPANIA, (v) => { state.cxc.COMPANIA = Number(v) || 0; }, { type: "number" })));
  tr.appendChild(td(textInput(c.RAMO, (v) => { state.cxc.RAMO = Number(v) || 0; }, { type: "number" })));
  tr.appendChild(td(textInput(c.SECUENCIAL, (v) => { state.cxc.SECUENCIAL = Number(v) || 0; }, { type: "number" })));
  tr.appendChild(td(textInput(c.FEC_INI, (v) => { state.cxc.FEC_INI = v; }, { type: "date" })));
  tr.appendChild(td(textInput(c.FEC_FIN, (v) => { state.cxc.FEC_FIN = v; }, { type: "date" })));
  tr.appendChild(td(moneyInput(c.VAL_NET, (n) => { state.cxc.VAL_NET = n; }), "num hl-green"));
  tr.appendChild(td(moneyInput(c.DES_NET, (n) => { state.cxc.DES_NET = n; }), "num"));
  tr.appendChild(td(moneyInput(c.IMPT_NET, (n) => { state.cxc.IMPT_NET = n; }), "num"));
  tr.appendChild(td(money(c.FACTURA_AMT), "num hl-calc readonly-val"));
  tr.appendChild(td(money(c.PTIMPORT2), "num hl-yellow hl-calc readonly-val"));
  tr.appendChild(td(money(c.BALANCE), "num hl-calc readonly-val"));

  tbody.appendChild(tr);
  document.getElementById("chipFactura").textContent = `FACTURA ${c.FACTURA} →`;
}

function renderTajuste() {
  const tbody = document.querySelector("#tableTajuste tbody");
  tbody.innerHTML = "";
  const t = state.tajuste;
  const tr = el("tr");

  tr.appendChild(td(textInput(t.NMAJUSTE, (v) => { state.tajuste.NMAJUSTE = Number(v) || 0; }, { type: "number" })));
  tr.appendChild(td(String(t.CLIENTE), "readonly-val"));
  tr.appendChild(td(String(t.COMPANIA), "readonly-val"));
  tr.appendChild(td(String(t.RAMO), "readonly-val"));
  tr.appendChild(td(String(t.SECUENCIAL), "readonly-val"));
  tr.appendChild(td(selectInput(t.TIP_DOC, ["FT", "NC", "ND", "RC"], (v) => { state.tajuste.TIP_DOC = v; }), "hl-yellow"));
  tr.appendChild(td(textInput(t.NMDOCUM, (v) => { state.tajuste.NMDOCUM = v; })));
  tr.appendChild(td(String(t.NMCUOREC), "readonly-val"));
  tr.appendChild(td(money(t.PTIMPORT), "num hl-yellow hl-calc readonly-val"));

  tbody.appendChild(tr);
  document.getElementById("chipAjuste").textContent = `NMAJUSTE ${t.NMAJUSTE} →`;
}

function renderTajudet() {
  const tbody = document.querySelector("#tableTajudet tbody");
  tbody.innerHTML = "";

  state.tajudet.forEach((row, idx) => {
    const tr = el("tr");
    tr.appendChild(td(String(row.NMAJUDET)));
    tr.appendChild(td(String(row.NMAJUSTE), "readonly-val"));
    tr.appendChild(td(String(row.COMPANIA), "readonly-val"));
    tr.appendChild(td(String(row.RAMO), "readonly-val"));
    tr.appendChild(td(String(row.SECUENCIAL), "readonly-val"));
    tr.appendChild(
      td(selectInput(row.TIPO_MOV, ["E", "I", "A", ""], (v) => { state.tajudet[idx].TIPO_MOV = v; }))
    );
    tr.appendChild(
      td(selectInput(row.CDTIPDOC, ["NC", "FT", "ND", ""], (v) => { state.tajudet[idx].CDTIPDOC = v; }))
    );
    tr.appendChild(td(textInput(row.TIPO_ASE, (v) => { state.tajudet[idx].TIPO_ASE = v; })));
    tr.appendChild(td(textInput(row.ASEGURADO, (v) => { state.tajudet[idx].ASEGURADO = v; })));
    tr.appendChild(td(textInput(row.DEPENDIENTE, (v) => { state.tajudet[idx].DEPENDIENTE = v; })));

    const amtClass =
      idx === 1 ? "num hl-green" : Math.abs(row.PTIMPORT) > 0 ? "num" : "num";
    tr.appendChild(
      td(
        moneyInput(row.PTIMPORT, (n) => {
          state.tajudet[idx].PTIMPORT = n;
        }),
        amtClass
      )
    );

    const del = el("button", {
      type: "button",
      className: "btn danger",
      title: "Eliminar línea",
      text: "×",
      onClick: () => {
        if (state.tajudet.length <= 1) return;
        state.tajudet.splice(idx, 1);
        renumberDet();
        render();
      },
    });
    tr.appendChild(td(del));
    tbody.appendChild(tr);
  });

  document.getElementById("sumTajudet").textContent = money(state.tajuste.PTIMPORT);
}

function renumberDet() {
  state.tajudet.forEach((r, i) => {
    r.NMAJUDET = i + 1;
  });
}

function renderRemesa() {
  const tbody = document.querySelector("#tableRemesa tbody");
  tbody.innerHTML = "";
  const r = state.remesa;
  const tr = el("tr");
  tr.appendChild(td(String(r.CDPERSON), "readonly-val"));
  tr.appendChild(td(String(r.CDUNIECO), "readonly-val"));
  tr.appendChild(td(String(r.CDRAMO), "readonly-val"));
  tr.appendChild(td(String(r.NMPOLFIN), "readonly-val"));
  tr.appendChild(td(String(r.NMCUOREC), "readonly-val"));
  tr.appendChild(
    td(selectInput(r.FOR_PAG, ["EFEC", "CHQ", "TRF", "TC"], (v) => { state.remesa.FOR_PAG = v; }))
  );
  tr.appendChild(
    td(
      moneyInput(r.PRIMPORT, (n) => { state.remesa.PRIMPORT = n; }),
      "num hl-green"
    )
  );
  tbody.appendChild(tr);
}

function renderStatus(checks) {
  const box = document.getElementById("statusCards");
  box.innerHTML = "";
  checks.forEach((c, i) => {
    const cls = c.ok ? "ok" : c.warn ? "warn" : "bad";
    const card = el("div", { className: `status-card ${cls}` });
    card.style.animationDelay = `${i * 0.04}s`;
    card.appendChild(el("span", { className: "label", text: c.label }));
    card.appendChild(el("span", { className: "value", text: c.value }));
    box.appendChild(card);
  });
}

function renderFlow() {
  const flow = document.getElementById("flowViz");
  flow.innerHTML = "";
  const nodes = [
    { title: "Σ TAJUDET", val: money(state.tajuste.PTIMPORT) },
    { title: "TAJUSTE.PTIMPORT", val: money(state.tajuste.PTIMPORT) },
    { title: "CXC.PTIMPORT2", val: money(state.cxc.PTIMPORT2) },
    { title: "CXC.BALANCE", val: money(state.cxc.BALANCE) },
    { title: "Tras remesa", val: money(state.saldoTrasRemesa) },
  ];
  nodes.forEach((n, i) => {
    if (i > 0) flow.appendChild(el("div", { className: "flow-arrow", text: "→" }));
    const node = el("div", { className: "flow-node" });
    node.appendChild(el("div", { className: "fn-title", text: n.title }));
    node.appendChild(el("div", { className: "fn-val", text: n.val }));
    flow.appendChild(node);
  });
}

function renderScenarios() {
  const list = document.getElementById("scenarioList");
  list.innerHTML = "";
  SCENARIOS.forEach((sc) => {
    const btn = el("button", {
      type: "button",
      className: activeScenario === sc.id ? "active" : "",
      onClick: () => {
        state = sc.apply();
        activeScenario = sc.id;
        render();
      },
    });
    btn.appendChild(el("strong", { text: sc.title }));
    btn.appendChild(el("span", { text: sc.desc }));
    list.appendChild(btn);
  });
}

function renderTargetLineSelect() {
  const sel = document.getElementById("targetLine");
  const prev = sel.value;
  sel.innerHTML = "";
  state.tajudet.forEach((r, i) => {
    const opt = el("option", {
      value: String(i),
      text: `#${r.NMAJUDET} (${r.TIPO_MOV || "?"} ${r.CDTIPDOC || ""})`,
    });
    sel.appendChild(opt);
  });
  if (prev !== "" && Number(prev) < state.tajudet.length) {
    sel.value = prev;
  } else {
    sel.value = "0";
  }
}

function render() {
  const { sumDet } = recompute(state);
  renderCxc();
  renderTajuste();
  renderTajudet();
  renderRemesa();
  renderStatus(statusChecks(state, sumDet));
  renderFlow();
  renderScenarios();
  renderTargetLineSelect();
}

/* ---------- Actions ---------- */

document.getElementById("btnAddDet").addEventListener("click", () => {
  const next = state.tajudet.length
    ? Math.max(...state.tajudet.map((r) => r.NMAJUDET)) + 1
    : 1;
  state.tajudet.push({
    NMAJUDET: next,
    NMAJUSTE: state.tajuste.NMAJUSTE,
    COMPANIA: state.cxc.COMPANIA,
    RAMO: state.cxc.RAMO,
    SECUENCIAL: state.cxc.SECUENCIAL,
    TIPO_MOV: "E",
    CDTIPDOC: "NC",
    TIPO_ASE: "",
    ASEGURADO: "",
    DEPENDIENTE: "",
    PTIMPORT: 0,
  });
  activeScenario = null;
  render();
});

document.getElementById("btnReset").addEventListener("click", () => {
  state = structuredClone(baseScenario());
  activeScenario = "captura";
  render();
});

document.getElementById("btnCuadrar").addEventListener("click", () => {
  const target = round2(parseMoney(document.getElementById("targetNet").value));
  const idx = Number(document.getElementById("targetLine").value);
  if (!state.tajudet[idx]) return;

  const others = round2(
    state.tajudet.reduce((acc, r, i) => (i === idx ? acc : acc + (Number(r.PTIMPORT) || 0)), 0)
  );
  state.tajudet[idx].PTIMPORT = round2(target - others);
  activeScenario = null;
  render();

  // Flash del pie de suma
  const sumCell = document.getElementById("sumTajudet");
  sumCell.classList.remove("flash");
  void sumCell.offsetWidth;
  sumCell.classList.add("flash");
});

document.getElementById("btnExport").addEventListener("click", () => {
  recompute(state);
  const blob = new Blob([JSON.stringify(state, null, 2)], { type: "application/json" });
  const a = document.createElement("a");
  a.href = URL.createObjectURL(blob);
  a.download = `cuadre-${state.cxc.FACTURA}.json`;
  a.click();
  URL.revokeObjectURL(a.href);
});

document.getElementById("fileImport").addEventListener("change", async (e) => {
  const file = e.target.files?.[0];
  if (!file) return;
  try {
    const text = await file.text();
    const data = JSON.parse(text);
    if (!data.cxc || !data.tajuste || !Array.isArray(data.tajudet) || !data.remesa) {
      throw new Error("JSON incompleto");
    }
    state = data;
    activeScenario = null;
    render();
  } catch (err) {
    alert("No se pudo importar el JSON: " + err.message);
  }
  e.target.value = "";
});

// Marcar edición manual al tocar inputs de detalle
document.querySelector("#tableTajudet").addEventListener(
  "change",
  () => {
    activeScenario = null;
  },
  true
);

render();
