(function () {
  "use strict";
  var form = document.getElementById("activityForm");
  if (!form) return;

  var items = Array.isArray(window.arActivityInitialItems) ? window.arActivityInitialItems.slice() : [];
  var list = document.getElementById("activityItems");
  var empty = document.getElementById("activityEmpty");
  var status = document.getElementById("activityStatus");
  var saveButton = document.getElementById("saveActivity");

  function announce(message, isError) {
    status.textContent = message;
    status.className = isError ? "small mb-3 text-danger" : "small mb-3 text-success";
  }

  function render() {
    list.textContent = "";
    empty.classList.toggle("d-none", items.length > 0);
    items.forEach(function (item, index) {
      var li = document.createElement("li");
      li.className = "list-group-item d-flex align-items-start justify-content-between gap-3";
      var copy = document.createElement("div");
      var title = document.createElement("strong");
      title.textContent = (index + 1) + ". " + (item.title || "Untitled Prompt");
      var text = document.createElement("div");
      text.className = "small text-muted";
      text.textContent = item.text || "";
      copy.appendChild(title);
      copy.appendChild(text);

      var actions = document.createElement("div");
      actions.className = "d-flex gap-1 flex-shrink-0";
      [
        { label: "Move up", symbol: "↑", delta: -1, disabled: index === 0 },
        { label: "Move down", symbol: "↓", delta: 1, disabled: index === items.length - 1 },
        { label: "Remove", symbol: "Remove", remove: true },
      ].forEach(function (action) {
        var button = document.createElement("button");
        button.type = "button";
        button.className = action.remove ? "btn btn-sm btn-outline-danger" : "btn btn-sm btn-outline-secondary";
        button.textContent = action.symbol;
        button.setAttribute("aria-label", action.label + " " + (item.title || "prompt"));
        button.disabled = Boolean(action.disabled);
        button.addEventListener("click", function () {
          if (action.remove) {
            items.splice(index, 1);
          } else {
            var other = index + action.delta;
            var moved = items.splice(index, 1)[0];
            items.splice(other, 0, moved);
          }
          render();
        });
        actions.appendChild(button);
      });
      li.appendChild(copy);
      li.appendChild(actions);
      list.appendChild(li);
    });
  }

  var addLibrary = document.getElementById("addLibraryPrompt");
  if (addLibrary) {
    addLibrary.addEventListener("click", function () {
      var select = document.getElementById("libraryPrompt");
      var option = select.options[select.selectedIndex];
      if (!option) return;
      var id = option.value;
      if (items.some(function (item) { return String(item.source_prompt_id || "") === id; })) {
        announce("That prompt is already in this activity.", true);
        return;
      }
      items.push({ source_prompt_id: id, title: option.text.split(" — ")[0], text: option.text });
      announce("Prompt added. Use the arrows to set its position.", false);
      render();
    });
  }

  document.getElementById("addNewPrompt").addEventListener("click", function () {
    var title = document.getElementById("newPromptTitle").value.trim();
    var text = document.getElementById("newPromptText").value.trim();
    var prepare = Number(document.getElementById("newPrepareTime").value);
    var response = Number(document.getElementById("newResponseTime").value);
    if (!text || !Number.isInteger(prepare) || !Number.isInteger(response) || prepare < 0 || response < 1 || prepare > 600 || response > 600) {
      announce("Enter prompt text and valid preparation and response times.", true);
      return;
    }
    items.push({
      new_prompt: {
        title: title,
        text: text,
        prepare_time: prepare,
        response_time: response,
        transcription: document.getElementById("newTranscription").checked,
        read_prompt: document.getElementById("newReadPrompt").checked,
      },
      title: title || "New prompt",
      text: text,
    });
    document.getElementById("newPromptTitle").value = "";
    document.getElementById("newPromptText").value = "";
    document.getElementById("newPromptDetails").open = false;
    announce("New prompt added to the activity.", false);
    render();
  });

  form.addEventListener("submit", function (event) {
    event.preventDefault();
    var title = document.getElementById("activity_title").value.trim();
    if (!title || items.length < 2 || items.length > 50) {
      announce("Enter an activity title and add between 2 and 50 prompts.", true);
      return;
    }
    var data = new FormData();
    data.append("activity_id", document.getElementById("activity_id").value);
    data.append("title", title);
    data.append("items", JSON.stringify(items.map(function (item) {
      if (item.activity_item_id) return { activity_item_id: item.activity_item_id };
      if (item.source_prompt_id) return { source_prompt_id: item.source_prompt_id };
      return { new_prompt: item.new_prompt };
    })));
    data.append("csrf_token", window.arCsrfToken || "");
    saveButton.disabled = true;
    saveButton.textContent = "Saving…";
    fetch("../phpScripts/saveActivity.php", { method: "POST", body: data, credentials: "same-origin" })
      .then(function (response) {
        return response.json().then(function (payload) {
          if (!response.ok || !payload.ok) throw new Error(payload.message || "Could not save activity.");
          return payload;
        });
      })
      .then(function (payload) {
        announce("Activity saved.", false);
        window.location.href = "responses.php?activity_id=" + encodeURIComponent(payload.activity_id);
      })
      .catch(function (error) {
        announce(error.message || "Could not save activity. Please try again.", true);
        saveButton.disabled = false;
        saveButton.textContent = "Save activity";
      });
  });

  render();
})();
