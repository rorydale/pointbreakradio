$(document).ready(function() {

  Handlebars.registerHelper('formatDate', function(date) {
    return moment(date, 'YYYY-MM-DD').format('dddd, MMMM Do, YYYY');
  });

  // Get view and render data
  function fetchDataAndRender(url, templateURL, targetElement) {
    // Retrieve the Handlebars template using AJAX
    $.ajax({
      url: templateURL,
      dataType: 'html',
      success: function(templateHtml) {
        // Compile the Handlebars template
        var template = Handlebars.compile(templateHtml);
  
        // Fetch data using AJAX
        $.ajax({
          url: url,
          dataType: 'json',
          success: function(data) {
            console.log(data);
            // Render the Handlebars template with the data
            var renderedHtml = template(data);
  
            // Insert the rendered HTML into the target element
            $(targetElement).html(renderedHtml);
          },
          error: function(xhr, status, error) {
            console.error('Error fetching data:', error);
          }
        });
      },
      error: function(xhr, status, error) {
        console.error('Error fetching template:', error);
      }
    });
  }


  // Render archive
  fetchDataAndRender('../github-api.php', '../views/archive.hbs', '#archive');


});