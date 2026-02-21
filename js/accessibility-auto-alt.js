"use strict";

(function () {
  function normalizeText(value) {
    return (value || "").replace(/\s+/g, " ").trim();
  }

  function toReadableText(value) {
    var text = normalizeText(value)
      .replace(/[-_]+/g, " ")
      .replace(/\s+/g, " ");

    if (!text) {
      return "";
    }

    return text.charAt(0).toUpperCase() + text.slice(1);
  }

  function filenameToLabel(src) {
    if (!src) {
      return "";
    }

    var path = src.split("#")[0].split("?")[0];
    var rawName = path.split("/").pop() || "";
    var name = rawName.replace(/\.[a-zA-Z0-9]+$/, "");

    try {
      name = decodeURIComponent(name);
    } catch (e) {
      // Keep original string if decoding fails.
    }

    return toReadableText(name);
  }

  function findNearbyLabel(element) {
    var i;
    var labelSelectors = [
      "figcaption",
      ".prompt-title",
      ".card-title",
      "h1",
      "h2",
      "h3",
      "h4",
      "h5",
      "h6",
      "label",
    ];

    var title = normalizeText(element.getAttribute("title"));
    if (title) {
      return title;
    }

    var ariaLabel = normalizeText(element.getAttribute("aria-label"));
    if (ariaLabel) {
      return ariaLabel;
    }

    var figure = element.closest("figure");
    if (figure) {
      var figcaption = figure.querySelector("figcaption");
      if (figcaption) {
        var captionText = normalizeText(figcaption.textContent);
        if (captionText) {
          return captionText;
        }
      }
    }

    var container = element.closest("section, article, .card, .row, .container, main, body");
    if (container) {
      for (i = 0; i < labelSelectors.length; i += 1) {
        var labeledElement = container.querySelector(labelSelectors[i]);
        if (labeledElement) {
          var labelText = normalizeText(labeledElement.textContent);
          if (labelText) {
            return labelText;
          }
        }
      }
    }

    return "";
  }

  function setImageAlt(image) {
    if (!(image instanceof HTMLImageElement) || image.hasAttribute("alt")) {
      return;
    }

    var label = findNearbyLabel(image) || filenameToLabel(image.currentSrc || image.src) || "Image";
    image.setAttribute("alt", label);
    image.setAttribute("data-auto-alt", "true");
  }

  function setTableLabel(table) {
    if (!(table instanceof HTMLTableElement)) {
      return;
    }

    if (normalizeText(table.getAttribute("aria-label")) || table.hasAttribute("aria-labelledby")) {
      return;
    }

    var caption = table.querySelector("caption");
    if (caption && normalizeText(caption.textContent)) {
      return;
    }

    var heading = findNearbyLabel(table);
    if (!heading) {
      var headerCell = table.querySelector("th");
      heading = headerCell ? normalizeText(headerCell.textContent) : "";
    }

    table.setAttribute("aria-label", heading || "Data table");
    table.setAttribute("data-auto-table-label", "true");
  }

  function processNode(rootNode) {
    if (!(rootNode instanceof Element)) {
      return;
    }

    if (rootNode.matches("img")) {
      setImageAlt(rootNode);
    }
    if (rootNode.matches("table")) {
      setTableLabel(rootNode);
    }

    rootNode.querySelectorAll("img").forEach(setImageAlt);
    rootNode.querySelectorAll("table").forEach(setTableLabel);
  }

  function runInitialScan() {
    processNode(document.body);
  }

  function startObservers() {
    var observer = new MutationObserver(function (mutations) {
      mutations.forEach(function (mutation) {
        if (mutation.type === "attributes" && mutation.target.matches("img")) {
          setImageAlt(mutation.target);
          return;
        }

        mutation.addedNodes.forEach(processNode);
      });
    });

    observer.observe(document.body, {
      subtree: true,
      childList: true,
      attributes: true,
      attributeFilter: ["src"],
    });
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", function () {
      runInitialScan();
      startObservers();
    });
  } else {
    runInitialScan();
    startObservers();
  }
})();
