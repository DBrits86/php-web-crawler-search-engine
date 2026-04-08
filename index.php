<!-- Developed by Danie Brits -->

<?php
session_start();

if (!isset($_SESSION['admin']) || $_SESSION['admin'] !== 'Authorized') {
    header('Location: login.html');
    exit();
}
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8" />
  <meta name='viewport' content='width=device-width, initial-scale=1.0'>
  <title>Media Search Engine</title>
 <style>
  body {
    font-family: 'Segoe UI', sans-serif;
    background: #f4f6f8;
    margin: 0;
    padding: 40px;
    color: #333;
  }
  h2 {
    margin-bottom: 20px;
    font-size: 28px;
    color: #444;
  }
  input, select, button {
    font-size: 16px;
    padding: 10px;
    margin: 5px;
    border-radius: 5px;
    border: 1px solid #ccc;
  }
  button {
    background: #007BFF;
    color: white;
    border: none;
    cursor: pointer;
  }
  button:hover {
    background: #0056b3;
  }
  .pagination {
    position: fixed;
    bottom: 10px;
    left: 50%;
    transform: translateX(-50%);
    background: white;
    padding: 10px 20px;
    border-radius: 8px;
    box-shadow: 0 0 10px rgba(0,0,0,0.1);
    z-index: 999;
    display: flex;
    gap: 10px;
  }
  .pagination button {
    margin: 0 10px;
  }

  .results {
    display: grid;
    grid-template-columns: repeat(4, 1fr); /* Desktop: 4 columns */
    gap: 20px;
    margin-top: 30px;
  }

  .result-item {
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    padding: 15px;
    transition: transform 0.2s;
  }

  .result-item:hover {
    transform: translateY(-3px);
  }

  .result-item img,
  .result-item video {
    max-width: 100%;
    max-height: 250px;
    width: auto;
    height: auto;
    border-radius: 5px;
    object-fit: contain;
    display: block;
    margin: 0 auto 10px;
    cursor: pointer;
  }

  .result-meta {
    margin-top: 10px;
    font-size: 14px;
    color: #666;
  }

  .result-domain {
    font-weight: bold;
    color: #007BFF;
    font-size: 13px;
    word-break: break-word;
  }

  .footer-info {
    text-align: center;
    margin-top: 40px;
    color: #777;
  }

  .search-bar {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    align-items: center;
    margin-bottom: 20px;
  }

  .search-bar input,
  .search-bar select,
  .search-bar button {
    flex: 1 1 auto;
    min-width: 120px;
  }

  /* Responsive breakpoints */
  @media (max-width: 900px) {
    .results {
      grid-template-columns: repeat(2, 1fr); /* Tablets: 2 columns */
    }
  }

  @media (max-width: 600px) {
    .search-bar {
      flex-direction: column;
      align-items: stretch;
    }
    .results {
      grid-template-columns: 1fr; /* Mobile: 1 column */
    }
  }
</style>
</head>
<body>
  <button onclick="window.location.href='search/index.html'">Go to Indexer</button>

  <h2>Media Search Engine</h2>
  <div class="search-bar">
    <input type="text" id="searchInput" placeholder="Search..." />
    <select id="typeSelect">
      <option value="word">All</option>
      <option value="image">Images</option>
      <option value="video">Videos</option>
    </select>
    <input type="text" id="domainInput" placeholder="Domain (optional)" />
    <button onclick="performSearch()">Search</button>
  </div>

  <div class="pagination">
    <button onclick="prevPage()">« Prev</button>
    <button onclick="nextPage()">Next »</button>
  </div>

  <div class="results" id="results"></div>

  <div class="footer-info" id="footer-info"></div>

  <script>
    let currentPage = 1;

    async function performSearch(page = 1) {
      const query = document.getElementById('searchInput').value.trim();
      const type = document.getElementById('typeSelect').value;
      const domain = document.getElementById('domainInput').value.trim();
      const limit = 25;  // Or get from a hidden input if you want dynamic limit
      const resultsDiv = document.getElementById('results');
      const footerInfo = document.getElementById('footer-info');
      currentPage = page;

      resultsDiv.innerHTML = "<p>Searching...</p>";
      footerInfo.textContent = "";

      try {
        const res = await fetch(`search_api.php?query=${encodeURIComponent(query)}&type=${type}&domain=${encodeURIComponent(domain)}&page=${page}&limit=${limit}`);
        const data = await res.json();
        resultsDiv.innerHTML = "";

        if (!data.results?.length) {
          resultsDiv.innerHTML = "<p>No results found.</p>";
          return;
        }

        data.results.forEach(item => {
          const element = document.createElement('div');
          element.className = 'result-item';

          if (type === 'word') {
            element.innerHTML = `
              <div>${item.text}</div>
              <div class="result-domain"><a href="${item.url}" target="_blank" rel="noopener noreferrer">${item.url}</a></div>
            `;
          } else if (type === 'image') {
            element.innerHTML = `
              <a href="${item.url}" target="_blank" rel="noopener noreferrer">
                <img src="${item.url}" alt="${item.alt || ''}" />
              </a>
              <div class="result-meta">${item.alt || '<i>No alt text</i>'}</div>
              <div class="result-domain">${item.domain}</div>
            `;
          } else if (type === 'video') {
            element.innerHTML = `
              <a href="${item.url}" target="_blank" rel="noopener noreferrer">
                <video controls src="${item.url}" alt="${item.alt || ''}" />
              </a>
              <div class="result-meta">${item.alt || '<i>No alt text</i>'}</div>
              <div class="result-domain">${item.domain}</div>
            `;
          }

          resultsDiv.appendChild(element);
        });

        const totalPages = Math.ceil(data.total / data.limit);
        footerInfo.innerHTML = `<strong>Page ${data.page} of ${totalPages}</strong> (${data.total} total results)`;

      } catch (err) {
        resultsDiv.innerHTML = "<p>Error fetching results.</p>";
        console.error(err);
      }
    }

    function nextPage() {
      performSearch(currentPage + 1);
    }

    function prevPage() {
      if (currentPage > 1) performSearch(currentPage - 1);
    }
  </script>
</body>
</html>
