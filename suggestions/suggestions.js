document.addEventListener('DOMContentLoaded', function() {
    loadNewSuggestions();
    loadPreviousSuggestions();

    // Add event listener for month filter
    document.getElementById('month-filter').addEventListener('change', function() {
        loadPreviousSuggestions(this.value);
    });

    // Add event listener for export button
    document.getElementById('export-button').addEventListener('click', function() {
        const selectedMonth = document.getElementById('month-filter').value;
        
        // Use fetch instead of direct window.location to handle the response
        fetch(`export_suggestions.php?months=${selectedMonth}`)
            .then(response => {
                const contentType = response.headers.get('content-type');
                if (contentType && contentType.includes('application/json')) {
                    // If it's JSON, there was an error
                    return response.json().then(data => {
                        alert(data.error);
                    });
                } else {
                    // If it's CSV, trigger the download
                    return response.blob().then(blob => {
                        const url = window.URL.createObjectURL(blob);
                        const a = document.createElement('a');
                        a.href = url;
                        a.download = selectedMonth ? 
                            `suggestions_${selectedMonth}.csv` : 
                            `suggestions_all_${new Date().toISOString().slice(0,10)}.csv`;
                        document.body.appendChild(a);
                        a.click();
                        window.URL.revokeObjectURL(url);
                        a.remove();
                    });
                }
            })
            .catch(error => {
                alert('An error occurred while exporting suggestions.');
                console.error('Error:', error);
            });
    });
});

function loadNewSuggestions() {
    fetch('get_suggestions.php?type=new')
        .then(response => response.json())
        .then(data => {
            const container = document.getElementById('new-suggestions');
            const countElement = document.getElementById('new-count');
            countElement.textContent = `${data.length} New`;
            if (data.length === 0) {
                container.innerHTML = '<div class="no-suggestions">No new suggestions</div>';
            } else {
                container.innerHTML = data.map(suggestion => createSuggestionCard(suggestion, true)).join('');
            }
        })
        .catch(error => console.error('Error:', error));
}

function loadPreviousSuggestions(months = '') {
    fetch(`get_suggestions.php?type=previous&months=${months}`)
        .then(response => response.json())
        .then(data => {
            const container = document.getElementById('previous-suggestions');
            const countElement = document.getElementById('previous-count');
            countElement.textContent = `${data.length} Previous`;
            
            if (data.length === 0) {
                container.innerHTML = '<div class="no-suggestions">No suggestions found.</div>';
            } else {
                container.innerHTML = data.map(suggestion => createSuggestionCard(suggestion, false)).join('');
            }
        })
        .catch(error => console.error('Error:', error));
}

function createSuggestionCard(suggestion, isNew) {
    return `
        <div class="suggestion-card">
            <div class="suggestion-header">
                <h3>${suggestion.name || 'Anonymous'}</h3>
                <span class="suggestion-date">${suggestion.submission_date}</span>
            </div>
            <div class="suggestion-content">
                <p>${suggestion.suggestion}</p>
            </div>
            <div class="suggestion-actions">
                ${isNew ? `<button class="action-button mark-read" onclick="markAsRead(${suggestion.id})">Mark as Read</button>` : ''}
                <button class="action-button delete" onclick="deleteSuggestion(${suggestion.id})">Delete</button>
            </div>
        </div>
    `;
}

function markAsRead(id) {
    fetch('update_suggestion.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            id: id,
            action: 'mark_read'
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Reload both sections to show the updated state
            loadNewSuggestions();
            loadPreviousSuggestions();
        }
    })
    .catch(error => console.error('Error:', error));
}

function deleteSuggestion(id) {
    if (confirm('Are you sure you want to delete this suggestion?')) {
        fetch('update_suggestion.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                id: id,
                action: 'delete'
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Reload both sections to show the updated state
                loadNewSuggestions();
                loadPreviousSuggestions();
            }
        })
        .catch(error => console.error('Error:', error));
    }
} 