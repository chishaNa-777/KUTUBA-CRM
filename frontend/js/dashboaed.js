document.addEventListener("DOMContentLoaded", function () {

    loadDashboardStats();

});


async function loadDashboardStats() {

    try {

        const response = await fetch(
            "../backend/dashboard/stats.php"
        );

        const data = await response.json();

        console.log("Dashboard data:", data);

        if (!data.success) {

            console.error(data.message);

            return;
        }


        // Clients
        document.getElementById("clientsCount").textContent =
            data.clients;


        // Workers
        document.getElementById("workersCount").textContent =
            data.workers;


        // Users
        document.getElementById("usersCount").textContent =
            data.users;


        // Total
        document.getElementById("totalCount").textContent =
            data.total;
        // Jobs
        document.getElementById("jobsCount").textContent =
            data.jobs;

        // Job Statuses
        document.getElementById("pendingCount").textContent =
            data.pending;

        document.getElementById("inProgressCount").textContent =
            data.in_progress;

        document.getElementById("completedCount").textContent =
            data.completed;

            document.getElementById("cancelledCount").textContent =
            data.cancelled;

    } catch (error) {

        console.error(
            "Unable to load dashboard statistics:",
            error
        );

    }

}