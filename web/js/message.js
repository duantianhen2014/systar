$(function(){
	page
	.on('sectioncreate','section[hash="message"]',function(){
		$(this).on('click','.message-dialog-list-item',function(){
			syssh.navigate('message/content/'+$(this).attr('id'),true);
		});
	})
	.on('sectioncreate','section[hash^="message/content"]',function(){
		window.clearInterval(polling.message);
		polling.message=window.setInterval(function(){
			$.post('/'+hash,{blocks:'content'});
		},3000);
	});
});