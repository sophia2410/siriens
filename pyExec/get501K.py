import requests
from bs4 import BeautifulSoup

url = "https://www.accessdata.fda.gov/scripts/cdrh/cfdocs/cfpmn/pmn.cfm?start_search=1&Center=&Panel=&ProductCode=&KNumber=&Applicant=&DeviceName=&Type=&ThirdPartyReviewed=&ClinicalTrials=&Decision=&DecisionDateFrom=12%2F25%2F2023&DecisionDateTo=01%2F01%2F2024&IVDProducts=&Redact510K=&CombinationProducts=&ZNumber=&PAGENUM=500"

response = requests.get(url)
soup = BeautifulSoup(response.text, 'html.parser')

links = soup.find_all('a', href=True)

for link in links:
    href = link['href']
    # print(href)
    if href.startswith('/scripts/cdrh/cfdocs/cfpmn/pmn.cfm?ID=K'):
        k_number = href.split('=')[-1]
        print(f'https://www.accessdata.fda.gov/scripts/cdrh/cfdocs/cfpmn/pmn.cfm?ID={k_number}')
        