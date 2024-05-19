// Informacja o załadowaniu skryptu
console.log("Skrypt załadowany");

// Zmienna przechowująca aktualnie wybrany typ transakcji
var currentTransactionType = "wydatek";

// Funkcja do wyświetlania sekcji wydatków
function showExpenses() {
  // Pobieranie elementów DOM
  var kategorieWydatkow = document.getElementById("kategorieWydatkow");
  var kategorieDochodow = document.getElementById("kategorieDochodow");
  var addCategoryForm = document.getElementById("addCategoryForm");

  // Zmiana widoczności elementów
  kategorieWydatkow.style.display = "block";
  kategorieDochodow.style.display = "none";
  if (addCategoryForm) addCategoryForm.style.display = "none";

  // Zmiana aktywnej zakładki
  document.getElementById("dochodyBtn").classList.remove("active");
  document.getElementById("wydatkiBtn").classList.add("active");

  // Ustawienie aktualnego typu transakcji
  currentTransactionType = "wydatek";
}

// Funkcja do wyświetlania sekcji dochodów
function showIncome() {
  var kategorieWydatkow = document.getElementById("kategorieWydatkow");
  var kategorieDochodow = document.getElementById("kategorieDochodow");
  var addCategoryForm = document.getElementById("addCategoryForm");

  kategorieWydatkow.style.display = "none";
  kategorieDochodow.style.display = "block";
  if (addCategoryForm) addCategoryForm.style.display = "none";
  document.getElementById("wydatkiBtn").classList.remove("active");
  document.getElementById("dochodyBtn").classList.add("active");

  currentTransactionType = "wydatek";
}

// Dodanie nasłuchiwaczy do przycisków wydatków i dochodów
document
  .getElementById("expensesButton")
  .addEventListener("click", showExpenses);
document.getElementById("incomeButton").addEventListener("click", showIncome);

// Funkcja do wyświetlania formularza dodawania kategorii
function showAddCategoryForm() {
  var kategorieWydatkow = document.getElementById("kategorieWydatkow");
  var kategorieDochodow = document.getElementById("kategorieDochodow");
  var addCategoryForm = document.getElementById("addCategoryForm");

  if (kategorieWydatkow && kategorieDochodow) {
    kategorieWydatkow.style.display = "none";
    kategorieDochodow.style.display = "none";
  }
  if (addCategoryForm) addCategoryForm.style.display = "block";
}

// Funkcja do przesyłania formularza budżetu
function submitBudget() {
  var amount = document.getElementById("budgetAmount").value;
  if (!amount || isNaN(amount) || amount < 0) {
    alert("Proszę wprowadzić prawidłową kwotę.");
    return;
  }
  // Tworzenie i konfiguracja zapytania AJAX do przesłania budżetu
  var xhr = new XMLHttpRequest();
  xhr.open("POST", "save_budget.php", true);
  xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
  xhr.onreadystatechange = function () {
    if (this.readyState == 4 && this.status == 200) {
      // Obsługa odpowiedzi serwera
      if (this.responseText === "Budżet zaktualizowany") {
        alert("Budżet został zaktualizowany.");
        closeModal(); // Zamknij modal
      } else {
        alert("Wystąpił błąd: " + this.responseText);
      }
    }
  };
  xhr.send("amount=" + encodeURIComponent(amount));
}

// Funkcja do wyświetlania modalu budżetu
function showBudgetModal() {
  var modal = document.getElementById("budgetModal");
  var backdrop = document.querySelector(".modal-backdrop");

  modal.style.display = "block";
  backdrop.style.display = "block";
}

// Funkcja do ustawiania budżetu w modalu
function showBudgetSetup() {
  var modalContent = document
    .getElementById("budgetModal")
    .getElementsByClassName("modal-content")[0];
  modalContent.innerHTML = `
        <h2>Ustaw swój budżet</h2>
        <input type="number" id="budgetAmount" placeholder="Wprowadź swój budżet" required style="margin-bottom: 20px;">
        <button onclick="submitBudget()" style="background-color: #3b9e3e; color: white;padding: 10px 20px; ">Zatwierdź</button>

    `;
}

// Funkcja do zamykania modalu
function closeModal() {
  var modal = document.getElementById("budgetModal");
  var backdrop = document.querySelector(".modal-backdrop");

  modal.style.display = "none";
  backdrop.style.display = "none";
}

// Funkcja do wyświetlania kategorii na podstawie typu transakcji
function showCategories(type) {
  currentTransactionType = type; // Aktualizacja globalnej zmiennej

  // Konfiguracja i wysłanie zapytania AJAX do pobrania kategorii
  var xhr = new XMLHttpRequest();
  xhr.onreadystatechange = function () {
    if (this.readyState == 4 && this.status == 200) {
      document.querySelector(".categories-summary").innerHTML =
        this.responseText;
      var chartData = processDataForChart(this.responseText);
      updateChartWithData(chartData);
    }
  };
  xhr.open("POST", "get_categories.php", true);
  xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
  xhr.send("type=" + type);
}

// Funkcja do filtrowania danych na podstawie wybranego okresu czasu
function filterByTime(timePeriod) {
  console.log("Filter by time:", timePeriod);

  // Konfiguracja i wysłanie zapytania AJAX do filtrowania danych
  var xhr = new XMLHttpRequest();
  xhr.onreadystatechange = function () {
    if (this.readyState == 4 && this.status == 200) {
      document.querySelector(".categories-summary").innerHTML =
        this.responseText;
      var chartData = processDataForChart(this.responseText);
      updateChartWithData(chartData);
    }
  };
  xhr.open("POST", "get_categories.php", true);
  xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
  xhr.send("type=" + currentTransactionType + "&timePeriod=" + timePeriod);
}

// Nasłuchiwacze do filtrowania danych na podstawie czasu
document.querySelectorAll(".time-filter-btn button").forEach(function (button) {
  button.addEventListener("click", function () {
    document
      .querySelectorAll(".time-filter-btn button")
      .forEach(function (btn) {
        btn.classList.remove("active");
      });
    this.classList.add("active");
    filterByTime(this.dataset.time); // Wywołanie filtrowania na podstawie dataset
  });
});

// Funkcja do dynamicznego dołączania skryptów na stronę
function includeScript(scriptName) {
  var script = document.createElement("script");
  script.src = scriptName; // Ustawia ścieżkę do skryptu
  script.classList.add("dynamic-script"); // Dodaje klasę dla łatwej identyfikacji
  document.body.appendChild(script); // Dołącza skrypt do <body> strony
}

// Funkcja do usuwania wcześniej dołączonych skryptów
function removePreviouslyIncludedScripts() {
  document
    .querySelectorAll(".dynamic-script") // Szuka wszystkich skryptów z klasą .dynamic-script
    .forEach((script) => script.remove()); // Usuwa znalezione skrypty
}

// Nasłuchiwanie na załadowanie całej zawartości DOM
document.addEventListener("DOMContentLoaded", function () {
  const wydatkiBtn = document.getElementById("wydatkiBtn");
  const dochodyBtn = document.getElementById("dochodyBtn");
  const kategorieWydatkow = document.getElementById("kategorieWydatkow");
  const kategorieDochodow = document.getElementById("kategorieDochodow");

  // Obsługa przełączania między wydatkami a dochodami
  wydatkiBtn.addEventListener("click", function () {
    kategorieWydatkow.style.display = "block";
    kategorieDochodow.style.display = "none";
  });

  dochodyBtn.addEventListener("click", function () {
    kategorieWydatkow.style.display = "none";
    kategorieDochodow.style.display = "block";
  });

    // Delegacja zdarzeń dla obsługi kliknięć w różnych miejscach głównego kontenera
  document
    .getElementById("mainContent")
    .addEventListener("click", function (event) {
      // Użycie event.target.closest() pozwala na obsługę kliknięć na dzieciach przycisków
      if (event.target.closest("#wydatkiBtn")) {
      // Funkcja showExpenses zmienia widoczność sekcji i aktualizuje aktywne zakładki
        showExpenses();
      } else if (event.target.closest("#dochodyBtn")) {
        showIncome();
      } else if (event.target.closest("#addCategoryBtn")) {
        // Funkcja showAddCategoryForm pokazuje formularz do dodawania nowych kategorii
        showAddCategoryForm();
      }
    });

  // Obsługa formularza dodawania kategorii z użyciem fetch API do asynchronicznego przesłania danych
  var addCategoryForm = document.getElementById("addCategoryForm");
  if (addCategoryForm) {
    addCategoryForm.addEventListener("submit", function (event) {
      event.preventDefault(); // Zapobieganie standardowemu zachowaniu formularza
      var formData = new FormData(this); // Utworzenie obiektu FormData z formularza

      // Asynchroniczne wysłanie danych do serwera
      fetch("categories.php", {
        method: "POST",
        body: formData,
      })
        .then((response) => response.text())
        .then((data) => {
          // Obsługa odpowiedzi z serwera
          if (data.includes("success")) {
            alert("Kategoria została dodana pomyślnie.");
            loadContent("categories.php");
          } else {
            alert("Wystąpił błąd podczas dodawania kategorii.");
          }
        })
        .catch((error) => {
          console.error("Error:", error);
        });
    });
  }

  // Delegacja zdarzeń dla przycisku 'Rozpocznij'
  if (sessionStorage.getItem("prompt_for_budget") === "true") {
    showBudgetModal();
  }

  // Ustawiamy domyślnie 'Wydatki' jako aktywne przy załadowaniu strony
  showExpenses();

  const timeFilterButtons = document.querySelectorAll(".time-filter-btn");
  timeFilterButtons.forEach((button) => {
    button.addEventListener("click", function () {
      // Usuń klasę 'active' ze wszystkich przycisków
      timeFilterButtons.forEach((btn) => btn.classList.remove("active"));
      // Dodaj klasę 'active' do klikniętego przycisku
      this.classList.add("active");
    });
  });
});
