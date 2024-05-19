// Funkcja do ładowania różnych stron w głównym kontenerze treści
function loadContent(page) {
  var mainContent = document.getElementById("mainContent");

  // Jeśli ładujemy home.php, użyj iframe
  if (page === "home.php") {
    // Specjalne traktowanie dla strony głównej - używamy iframe
    mainContent.innerHTML =
      '<iframe src="home.php" width="100%" height="600" frameborder="0"></iframe>';
  } else {
    // Dla innych stron używamy XMLHttpRequest do asynchronicznego ładowania zawartości
    var xhr = new XMLHttpRequest();
    xhr.onreadystatechange = function () {
      if (this.readyState == 4 && this.status == 200) {
        mainContent.innerHTML = this.responseText;
      }
    };
    xhr.open("GET", page, true);
    xhr.send();
  }
}

// Definicja kolorów dla wykresu
var chartColors = [
  "#FF6384",
  "#36A2EB",
  "#FFCE56",
  "#4BC0C0",
  "#9966FF",
  "#FF9F40",
  "#FF6384",
  "#C9CBCF",
  "#7BCDBA",
  "#FFD1DC",
  "#B9F2FF",
  "#FA8072",
  "#FFD700",
  "#ADFF2F",
  "#7FFFD4",
  "#D2691E",
  "#FF69B4",
  "#8A2BE2",
  "#00FF7F",
  "#DC143C",
  "#00008B",
  "#008B8B",
  "#B8860B",
  "#A9A9A9",
  "#006400",
];


// Inicjalizacja zmiennej wykresu
var myDoughnutChart = null;


// Funkcja tworząca wykres typu doughnut (pączkowy)
function createChart(chartData) {
  var ctx = document.getElementById("myDoughnutChart").getContext("2d");
  myDoughnutChart = new Chart(ctx, {
    type: "doughnut",
    data: {
      labels: chartData.labels, // Etykiety wykresu
      datasets: [
        {
          label: "Kategorie",
          data: chartData.values, // Dane wykresu
          backgroundColor: chartColors, // Kolory tła dla danych
          borderWidth: 1,
        },
      ],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
    },
  });
}

// Funkcja aktualizująca wykres z nowymi danymi
function updateChartWithData(chartData) {
  if (myDoughnutChart) {
    myDoughnutChart.destroy(); // Niszczenie starego wykresu przed stworzeniem nowego
  }
  createChart(chartData); // Tworzenie nowego wykresu z aktualnymi danymi
}

// Funkcja przetwarzająca dane HTML na format używany przez wykres
function processDataForChart(htmlResponse) {
  var parser = new DOMParser();
  var doc = parser.parseFromString(htmlResponse, "text/html");
  var categories = doc.querySelectorAll("p");
  var labels = [];
  var values = [];

  categories.forEach(function (cat) {
    var text = cat.innerText.split(" - "); // Rozdzielenie tekstu na etykietę i wartość
    labels.push(text[0]); // Dodawanie etykiety
    values.push(parseFloat(text[1])); // Dodawanie wartości po konwersji na liczb
  });

  return {
    labels: labels,
    values: values,
  };
}

// Obsługa modalnego okna dodawania transakcji
document.addEventListener("DOMContentLoaded", function () {
  var modal = document.getElementById("addTransactionModal");
  var btn = document.querySelector(".add-transaction-button");
  var span = document.getElementsByClassName("close-button")[0];

  // Wyświetlanie modala po kliknięciu przycisku
  btn.onclick = function () {
    modal.style.display = "block";
  };
  // Zamykanie modala po kliknięciu na przycisk zamknięcia
  span.onclick = function () {
    modal.style.display = "none";
  };
  // Zamykanie modala po kliknięciu poza obszarem modala
  window.onclick = function (event) {
    if (event.target == modal) {
      modal.style.display = "none";
    }
  };
  // Aktualizacja opcji wyboru kategorii w zależności od typu transakcji
  var transactionTypeSelect = document.getElementById("transactionType");
  var categorySelect = document.getElementById("categorySelect");

  // Pobieranie danych kategorii zapisanych w atrybutach data- elementu DOM
  var categoriesData = document.getElementById("categoriesData");
  var categories = {
    wydatki: JSON.parse(categoriesData.getAttribute("data-wydatki")),
    dochody: JSON.parse(categoriesData.getAttribute("data-dochody")),
  };
  function closeModal() {
    modal.style.display = "none";
  }

  // Aktualizacja opcji kategorii
  function updateCategoryOptions(type) {
    // Usunięcie istniejących opcji z selektora
    while (categorySelect.options.length > 0) {
      categorySelect.remove(0);
    }

    // Dodanie nowych opcji do selektora na podstawie typu transakcji
    var optionList =
      type === "wydatek" ? categories.wydatki : categories.dochody;
    optionList.forEach(function (category) {
      var option = new Option(category.nazwa, category.id);
      categorySelect.add(option);
    });
  }
  // Początkowe załadowanie opcji kategorii
  updateCategoryOptions("wydatek");

  // Obsługa zmiany typu transakcji i aktualizacja opcji kategorii
  transactionTypeSelect.addEventListener("change", function () {
    updateCategoryOptions(this.value);
  });

  // Ustawienie dzisiejszej i wczorajszej daty w formularzu
  var todayButton = document.getElementById("todayButton");
  var yesterdayButton = document.getElementById("yesterdayButton");
  var transactionDate = document.getElementById("transactionDate");

  // Ustawienie dzisiejszej daty
  todayButton.addEventListener("click", function () {
    transactionDate.valueAsDate = new Date();
  });

  // Ustawienie wczorajszej daty
  yesterdayButton.addEventListener("click", function () {
    var yesterday = new Date();
    yesterday.setDate(yesterday.getDate() - 1);
    transactionDate.valueAsDate = yesterday;
  });

  // Obsługa wysyłania formularza dodawania transakcji
  var form = document.getElementById("addTransactionForm");
  form.onsubmit = function (event) {
    event.preventDefault(); // Zapobieganie standardowemu zachowaniu formularza

    var formData = new FormData(form); // Tworzenie FormData z formularza

    if (idRodziny) {
      formData.append("id_rodziny", idRodziny); // Dodanie id rodziny, jeśli istnieje
    }
    // Ustawienie wartości daty w formacie 'YYYY-MM-DD', jeśli nie została wybrana przez input typu 'date'
    if (!formData.get("data")) {
      var today = new Date();
      formData.set("data", today.toISOString().split("T")[0]);
    }

    // Wykonanie zapytania Fetch do przesłania danych formularza na serwer
    fetch("submit_transaction.php", {
      method: "POST",
      body: formData,
      headers: {
        Accept: "application/json",
      },
    })
      .then((response) => response.json())
      .then((data) => {
        console.log(data); // Logowanie odpowiedzi serwera
        closeModal(); // Zamknięcie modala
        location.reload(); // Odświeżenie strony
      })
      .catch((error) => {
        console.error("Error:", error);
        // Obsługa błędów
      });
  };
});
